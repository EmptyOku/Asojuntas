import os
import sys
import json
import base64
import re
import argparse
import unicodedata
import hashlib
from pathlib import Path

# ==========================================
# GESTIÓN DE ENTORNO Y DEPENDENCIAS
# ==========================================
try:
    import boto3
    from dotenv import load_dotenv
except ImportError:
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "boto3", "python-dotenv"])
    import boto3
    from dotenv import load_dotenv

def initialize_environment():
    current_working_dir = Path.cwd()
    env_path_cwd = current_working_dir / ".env"
    if env_path_cwd.exists():
        load_dotenv(env_path_cwd)
        return

    script_dir = Path(__file__).resolve().parent
    current_dir = script_dir
    for _ in range(3):
        env_path = current_dir / ".env"
        if env_path.exists():
            load_dotenv(env_path)
            return
        current_dir = current_dir.parent
    load_dotenv()

initialize_environment()

# ==========================================
# CONFIGURACIÓN DE INFRAESTRUCTURA AWS
# ==========================================
AWS_ACCESS_KEY  = os.getenv("AWS_ACCESS_KEY_ID")
AWS_SECRET_KEY  = os.getenv("AWS_SECRET_ACCESS_KEY")
AWS_REGION      = os.getenv("AWS_DEFAULT_REGION", "us-east-1")
BEDROCK_MODEL_ID = os.getenv("BEDROCK_MODEL_ID", "anthropic.claude-3-haiku-20240307-v1:0")

if not AWS_ACCESS_KEY or not AWS_SECRET_KEY:
    sys.stderr.write("[CRITICAL] Variables de entorno AWS_ACCESS_KEY_ID o AWS_SECRET_ACCESS_KEY no definidas.\n")
    sys.exit(1)

aws_client = boto3.client(
    "bedrock-runtime",
    aws_access_key_id=AWS_ACCESS_KEY,
    aws_secret_access_key=AWS_SECRET_KEY,
    region_name=AWS_REGION
)

# ==========================================
# DEFINICIÓN DE ESQUEMA JSON (TOOL USE SCHEMA)
# ==========================================
EXTRACTION_SCHEMA = {
    "name": "parse_electoral_document",
    "description": "Parser estructurado para documentos de registro electoral.",
    "input_schema": {
        "type": "object",
        "properties": {
            "auditoria": {
                "type": "object",
                "description": "Modulo de evaluacion de calidad OCR.",
                "properties": {
                    "confidence_score": {
                        "type": "integer", 
                        "description": "Indice de legibilidad (0-100). Penalizar por manuscritos borrosos. Ignorar firmas."
                    },
                    "anomaly_flag": {
                        "type": "string", 
                        "description": "Registro de anomalias. Por defecto: 'None'."
                    }
                },
                "required": ["confidence_score", "anomaly_flag"]
            },
            "candidatos_detectados": {
                "type": "array",
                "description": "Vector de entidades (candidatos) detectadas en el documento.",
                "items": {
                    "type": "object",
                    "properties": {
                        "bloque_num":    {"type": "integer", "description": "Identificador numérico del bloque."},
                        "bloque_nombre": {"type": "string",  "description": "Etiqueta descriptiva del bloque orgánico."},
                        "cargo":         {"type": "string",  "description": "Rol asignado exactamente como aparece en el documento (e.g., FISCAL, SUPLENTE FISCAL, CONCILIADOR 1, COMISION EMPRESARIAL)."},
                        "nombre":        {"type": "string"},
                        "identificacion":{"type": "string"},
                        "celular":       {"type": "string"},
                        "correo":        {"type": "string"}
                    },
                    "required": ["cargo", "nombre", "identificacion"]
                }
            }
        },
        "required": ["auditoria", "candidatos_detectados"]
    }
}

# ==========================================
# POLÍTICAS DE CONTEXTO (SYSTEM DIRECTIVES)
# ==========================================
CONTEXT_POLICIES = [
    "Block 1 valid cargo labels: 'PRESIDENTE', 'VICEPRESIDENTE', 'TESORERO', 'SECRETARIO'.",
    "Block 2 valid cargo labels: 'SUPLENTE PRESIDENTE', 'DELEGADO ASOJUNTAS 1', 'SUPLENTE DELEGADO ASOJUNTAS 1', 'DELEGADO ASOJUNTAS 2', 'SUPLENTE DELEGADO ASOJUNTAS 2', 'DELEGADO ASOJUNTAS 3', 'SUPLENTE DELEGADO ASOJUNTAS 3'.",
    "Block 3 valid cargo labels ONLY: 'FISCAL' and 'SUPLENTE FISCAL'. IMPORTANT: 'SUPLENTE FISCAL' is a different role from 'FISCAL'. Never merge them.",
    "Block 4 valid cargo labels ONLY: 'CONCILIADOR 1', 'CONCILIADOR 2', 'CONCILIADOR 3', 'COMISION EMPRESARIAL'. Convert Roman numerals to Arabic (I -> 1, II -> 2).",
    "Apply Arabic numeral normalization (Convert I, II, III to 1, 2, 3).",
    "Bypass signature stroke analysis during OCR parsing.",
    "Return EXACT cargo labels as specified above. Do not paraphrase."
]

SYSTEM_DIRECTIVE_CONFIG = "\n".join(CONTEXT_POLICIES)

# ==========================================
# CAPA DE NORMALIZACIÓN
# ==========================================
def normalize_text_string(text_input: str) -> str:
    """Normaliza NFD y elimina acentos. Conserva espacios para matching exacto."""
    t = str(text_input).upper().strip()
    t = ''.join(c for c in unicodedata.normalize('NFD', t) if unicodedata.category(c) != 'Mn')
    t = re.sub(r'\bI\b',   '1', t)
    t = re.sub(r'\bII\b',  '2', t)
    t = re.sub(r'\bIII\b', '3', t)
    t = re.sub(r'\([AaOo]\)', '', t)
    t = re.sub(r'\s+', ' ', t).strip()
    return t

def infer_media_type(image_path: Path) -> str:
    ext = image_path.suffix.lower()
    if ext == ".png":  return "image/png"
    if ext == ".webp": return "image/webp"
    return "image/jpeg"

# ==========================================
# MOTOR DE CLASIFICACIÓN
# ==========================================
ROUTING_TABLE = {
    "PRESIDENTE":              (0, 0, "Presidente"),
    "VICEPRESIDENTE":          (0, 1, "Vicepresidente"),
    "TESORERO":                (0, 2, "Tesorero"),
    "SECRETARIO":              (0, 3, "Secretario"),
    "SUPLENTE PRESIDENTE":              (1, 0, "Suplente Presidente"),
    "SUPLENTE DELEGADO ASOJUNTAS 1":    (1, 2, "Suplente Delegado Asojuntas 1"),
    "SUPLENTE DELEGADO ASOJUNTAS 2":    (1, 4, "Suplente Delegado Asojuntas 2"),
    "SUPLENTE DELEGADO ASOJUNTAS 3":    (1, 6, "Suplente Delegado Asojuntas 3"),
    "DELEGADO ASOJUNTAS 1":             (1, 1, "Delegado Asojuntas 1"),
    "DELEGADO ASOJUNTAS 2":             (1, 3, "Delegado Asojuntas 2"),
    "DELEGADO ASOJUNTAS 3":             (1, 5, "Delegado Asojuntas 3"),
    "SUPLENTE FISCAL":         (2, 1, "Suplente Fiscal"),
    "FISCAL":                  (2, 0, "Fiscal"),
    "COMISION EMPRESARIAL":    (3, 3, "Comisión Empresarial"),
    "CONCILIADOR 1":           (3, 0, "Conciliador 1"),
    "CONCILIADOR 2":           (3, 1, "Conciliador 2"),
    "CONCILIADOR 3":           (3, 2, "Conciliador 3"),
    "CONCILIADOR":             (3, 99, "Conciliador (revisar índice)"),
}

SORTED_ROUTING_KEYS = sorted(ROUTING_TABLE.keys(), key=len, reverse=True)

def process_classification_engine(raw_candidates: list) -> list:
    """Clasifica, deduplica mediante Hash MD5 y ordena candidatos."""
    structured_output = [
        {"bloque_num": 1, "bloque_nombre": "Bloque 1 - Directiva",                              "candidatos": []},
        {"bloque_num": 2, "bloque_nombre": "Bloque 2 - Delegados Asojuntas",                    "candidatos": []},
        {"bloque_num": 3, "bloque_nombre": "Bloque 3 - Fiscal",                                 "candidatos": []},
        {"bloque_num": 4, "bloque_nombre": "Bloque 4 - Comisión de convivencia y conciliación", "candidatos": []},
        {"bloque_num": 99,"bloque_nombre": "Cargos No Reconocidos",                             "candidatos": []},
    ]

    seen_fingerprints = set()

    for cand in raw_candidates:
        # Prevencion de duplicados
        nombre = cand.get('nombre', '').strip()
        identificacion = cand.get('identificacion', '').strip()
        cargo = cand.get('cargo', '').strip()
        
        fingerprint = hashlib.md5(f"{nombre}{identificacion}{cargo}".encode()).hexdigest()
        if fingerprint in seen_fingerprints:
            continue
        seen_fingerprints.add(fingerprint)

        raw_cargo       = cand.get("cargo", "")
        normalized_cargo = normalize_text_string(raw_cargo)
        is_assigned     = False

        for key in SORTED_ROUTING_KEYS:
            normalized_key = normalize_text_string(key)
            if normalized_key == normalized_cargo or normalized_key in normalized_cargo:
                block_idx, order_idx, official_label = ROUTING_TABLE[key]
                structured_output[block_idx]["candidatos"].append({
                    "cargo":          official_label,
                    "nombre":         cand.get("nombre", "").strip(),
                    "identificacion": cand.get("identificacion", "").strip(),
                    "celular":        cand.get("celular", "").strip(),
                    "correo":         cand.get("correo", "").strip(),
                    "foto_origen":    cand.get("foto_origen", ""),
                    "_sort_index":    order_idx,
                })
                is_assigned = True
                break

        if not is_assigned:
            cand["_sort_index"] = 999
            structured_output[4]["candidatos"].append(cand)

    for block in structured_output:
        block["candidatos"].sort(key=lambda x: x.get("_sort_index", 999))
        for c in block["candidatos"]:
            c.pop("_sort_index", None)

    if not structured_output[4]["candidatos"]:
        structured_output.pop()

    return structured_output


def normalize_plancha_payload(structured_blocks: list) -> dict:
    """Convierte la salida clasificada a un payload homogéneo para Laravel/Vue."""
    plancha_blocks = []
    elected_people = []

    for block in structured_blocks:
        block_name = str(block.get("bloque_nombre", "SIN BLOQUE")).strip() or "SIN BLOQUE"
        block_candidates = []

        for cand in block.get("candidatos", []):
            cargo = str(cand.get("cargo", "")).strip()
            nombre = str(cand.get("nombre", "")).strip()
            identificacion = str(cand.get("identificacion", "")).strip()
            celular = str(cand.get("celular", "")).strip()
            correo = str(cand.get("correo", "")).strip()

            if not cargo and not nombre:
                continue

            block_candidates.append({
                "puesto": cargo,
                "nombre": nombre,
                "identificacion": identificacion,
                "celular": celular,
                "correo": correo,
            })

            if nombre:
                parts = nombre.split()
                first_name = parts[0] if parts else ""
                last_name = " ".join(parts[1:]) if len(parts) > 1 else "SIN_APELLIDO"

                elected_people.append({
                    "first_name": first_name,
                    "last_name": last_name,
                    "document_number": identificacion or None,
                    "phone": celular or None,
                    "email": correo or None,
                    "notes": f"Cargo OCR: {cargo or 'SIN_CARGO'}",
                    "review_status": "pending",
                })

        if block_candidates:
            plancha_blocks.append({
                "titulo": f"Bloque - {block_name}",
                "cargos": block_candidates,
            })

    return {
        "block_results": [],
        "block_votes": [],
        "elected_people": elected_people,
        "plancha_blocks": plancha_blocks,
    }

# ==========================================
# INVOCACIÓN AWS BEDROCK
# ==========================================
def invoke_bedrock_parsing(image_path: Path):
    sys.stderr.write(f"[INFO] Initializing parsing routine for: {image_path.name}\n")

    with image_path.open("rb") as f:
        encoded_image = base64.b64encode(f.read()).decode("utf-8")

    payload = {
        "anthropic_version": "bedrock-2023-05-31",
        "max_tokens": 4096,
        "temperature": 0.0,
        "system": SYSTEM_DIRECTIVE_CONFIG,
        "messages": [
            {
                "role": "user",
                "content": [
                    {
                        "type": "image",
                        "source": {
                            "type": "base64",
                            "media_type": infer_media_type(image_path),
                            "data": encoded_image,
                        },
                    },
                    {
                        "type": "text",
                        "text": (
                            "Analyze this electoral registration document. "
                            "Extract ALL candidates visible. "
                            "Use EXACT cargo labels as defined in your system instructions. "
                            "For Block 3: use 'FISCAL' or 'SUPLENTE FISCAL' only. "
                            "For Block 4: use 'CONCILIADOR 1', 'CONCILIADOR 2', 'CONCILIADOR 3', or 'COMISION EMPRESARIAL' only."
                        ),
                    },
                ],
            }
        ],
        "tools": [EXTRACTION_SCHEMA],
        "tool_choice": {"type": "tool", "name": "parse_electoral_document"},
    }

    try:
        response      = aws_client.invoke_model(modelId=BEDROCK_MODEL_ID, body=json.dumps(payload))
        response_body = json.loads(response["body"].read())

        for content in response_body.get("content", []):
            if content.get("type") == "tool_use" and content.get("name") == "parse_electoral_document":
                parsed_data = content.get("input", {})
                candidates_list = parsed_data.get("candidatos_detectados", [])
                audit_data = parsed_data.get("auditoria", {})
                
                sys.stderr.write(f"[SUCCESS] Entities detected: {len(candidates_list)}\n")
                for c in candidates_list:
                    c["foto_origen"] = image_path.name
                    
                audit_data["status"] = "SUCCESS"
                return candidates_list, audit_data

        sys.stderr.write(f"[WARN] Schema binding failed for {image_path.name}\n")
        return [], {"status": "SCHEMA_BINDING_FAILURE", "confidence_score": 0, "anomaly_flag": "Schema missing"}

    except Exception as e:
        sys.stderr.write(f"[ERROR] Exception on {image_path.name}: {str(e)}\n")
        return [], {"status": "EXCEPTION", "confidence_score": 0, "anomaly_flag": str(e)}

# ==========================================
# FLUJO PRINCIPAL BATCH
# ==========================================
def batch_process_directory(target_directory: str):
    target_path = Path(target_directory).resolve()
    
    if not target_path.exists() or not target_path.is_dir():
        sys.stderr.write(f"[ERROR] Invalid directory: {target_path}\n")
        sys.exit(1)

    # Identificación universal de imágenes
    valid_extensions = {".jpg", ".jpeg", ".png", ".webp"}
    image_files = sorted(
        [f for f in target_path.iterdir() if f.is_file() and f.suffix.lower() in valid_extensions],
        key=lambda x: x.name
    )

    if not image_files:
        sys.stderr.write(f"[ERROR] Target queue is empty. No valid images found in {target_path}\n")
        sys.exit(1)

    sys.stdout.write(f"[INFO] Batch queue: {len(image_files)} image(s).\n\n")

    global_pool    = []
    execution_logs = []

    for file in image_files:
        candidates, audit_info = invoke_bedrock_parsing(file)
        global_pool.extend(candidates)
        
        execution_logs.append({
            "source_file":      file.name,
            "operation_status": audit_info.get("status", "UNKNOWN"),
            "confidence_score": audit_info.get("confidence_score", 0),
            "anomaly_flag":     audit_info.get("anomaly_flag", "None")
        })

    consolidated = process_classification_engine(global_pool)

    final_response = {
        "status":         "200_OK",
        "execution_logs": execution_logs,
        "payload":        consolidated,
    }

    sys.stdout.write("\n" + "=" * 80 + "\n")
    sys.stdout.write("                    FINAL SERIALIZED OUTPUT\n")
    sys.stdout.write("=" * 80 + "\n")
    sys.stdout.write(json.dumps(final_response, indent=2, ensure_ascii=False) + "\n")
    sys.stdout.write("=" * 80 + "\n")


def process_single_image(image_path: str, dry_run: bool):
    image = Path(image_path).resolve()
    if not image.exists() or not image.is_file():
        sys.stderr.write(json.dumps({"error": f"Imagen invalida: {image}"}))
        sys.exit(1)

    candidates, audit_info = invoke_bedrock_parsing(image)
    structured = process_classification_engine(candidates)
    normalized_payload = normalize_plancha_payload(structured)

    if dry_run:
        sys.stdout.write(json.dumps({"normalized_payload": normalized_payload}, ensure_ascii=True, indent=2))
        return

    response = {
        "status": "200_OK",
        "execution_logs": [
            {
                "source_file": image.name,
                "operation_status": audit_info.get("status", "UNKNOWN"),
                "confidence_score": audit_info.get("confidence_score", 0),
                "anomaly_flag": audit_info.get("anomaly_flag", "None"),
            }
        ],
        "payload": structured,
        "normalized_payload": normalized_payload,
    }
    sys.stdout.write(json.dumps(response, ensure_ascii=False, indent=2))

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Extractor OCR de candidatos (planchas)")
    parser.add_argument("source_directory", nargs="?", help="Directorio con imagenes (modo batch)")
    parser.add_argument("--image", type=str, help="Ruta de una imagen individual")
    parser.add_argument("--dry-run", action="store_true", help="Imprime solo normalized_payload")
    args = parser.parse_args()

    if args.image:
        process_single_image(args.image, args.dry_run)
        sys.exit(0)

    if args.source_directory:
        batch_process_directory(args.source_directory)
        sys.exit(0)

    sys.stderr.write(
        "[ERROR] Debes indicar <source_directory> o --image <ruta_imagen>.\n"
    )
    sys.exit(1)