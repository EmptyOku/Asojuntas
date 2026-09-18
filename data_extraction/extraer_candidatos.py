import os
import sys
import json
import base64
import mimetypes
import re
import argparse
import unicodedata
import hashlib
import time
from pathlib import Path

# ==========================================
# GESTIÓN DE ENTORNO Y DEPENDENCIAS
# ==========================================
try:
    from dotenv import load_dotenv
except ImportError:
    def load_dotenv(*_args, **_kwargs):
        return False

def load_project_env() -> Path:
    current_working_dir = Path.cwd()
    env_path_cwd = current_working_dir / ".env"
    if env_path_cwd.exists():
        load_dotenv(env_path_cwd)
        return current_working_dir

    script_dir = Path(__file__).resolve().parent
    current_dir = script_dir
    for _ in range(3):
        env_path = current_dir / ".env"
        if env_path.exists():
            load_dotenv(env_path)
            return current_dir
        current_dir = current_dir.parent
    load_dotenv()
    return script_dir.parent

load_project_env()

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
                        "cargo":         {"type": "string",  "description": "Rol asignado exactamente como aparece en el documento."},
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
    "Block 2 valid cargo labels: 'SUPLENTE DE PRESIDENTE', 'SUPLENTE PRESIDENTE', 'DELEGADO ASOJUNTAS 1', 'SUPLENTE DELEGADO ASOJUNTAS 1', 'DELEGADO ASOJUNTAS 2', 'SUPLENTE DELEGADO ASOJUNTAS 2', 'DELEGADO ASOJUNTAS 3', 'SUPLENTE DELEGADO ASOJUNTAS 3'.",
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
    """
    Normaliza NFD y elimina acentos, PERO conserva la letra Ñ.
    Conserva espacios para matching exacto.
    """
    t = str(text_input).upper().strip()
    # Preservar la Ñ sustituyéndola temporalmente
    t = t.replace('Ñ', '##ENYE##')
    # Quitar acentos
    t = ''.join(c for c in unicodedata.normalize('NFD', t) if unicodedata.category(c) != 'Mn')
    # Restaurar la Ñ
    t = t.replace('##ENYE##', 'Ñ')
    
    t = re.sub(r'\bI\b',   '1', t)
    t = re.sub(r'\bII\b',  '2', t)
    t = re.sub(r'\bIII\b', '3', t)
    t = re.sub(r'\([AaOo]\)', '', t)
    t = re.sub(r'\s+', ' ', t).strip()
    return t

def infer_media_type(image_path: Path) -> str:
    guessed, _ = mimetypes.guess_type(str(image_path))
    if guessed:
        return guessed
    return "image/jpeg"

def build_bedrock_error_message(exc: Exception, region: str, model_id: str) -> str:
    error_text = str(exc).strip()
    if exc.__class__.__name__ == "EndpointConnectionError" or "Could not connect" in error_text:
        return f"No se pudo conectar a AWS Bedrock en la region {region} usando el modelo {model_id}."
    if exc.__class__.__name__ == "ClientError":
        response = getattr(exc, "response", {}) or {}
        error_data = response.get("Error", {}) if isinstance(response, dict) else {}
        code = str(error_data.get("Code", "")).strip()
        message = str(error_data.get("Message", error_text)).strip()
        return f"Bedrock rechazo la solicitud ({code or 'ClientError'}): {message}"
    return error_text

def is_retryable_bedrock_error(exc: Exception) -> bool:
    if exc.__class__.__name__ in {"EndpointConnectionError", "ConnectTimeoutError", "ReadTimeoutError"}:
        return True
    if exc.__class__.__name__ == "ClientError":
        response = getattr(exc, "response", {}) or {}
        error_data = response.get("Error", {}) if isinstance(response, dict) else {}
        return str(error_data.get("Code", "")).strip() in {
            "InternalServerException", "ModelTimeoutException",
            "ServiceUnavailableException", "ThrottlingException", "TooManyRequestsException",
        }
    return False

def invoke_model_with_retry(client, model_id: str, payload: dict):
    max_attempts = max(1, int(os.getenv("BEDROCK_MAX_RETRIES", "3")))
    base_delay = max(0.2, float(os.getenv("BEDROCK_RETRY_BASE_SECONDS", "1.2")))
    for attempt in range(1, max_attempts + 1):
        try:
            return client.invoke_model(modelId=model_id, body=json.dumps(payload))
        except Exception as exc:
            if attempt >= max_attempts or not is_retryable_bedrock_error(exc):
                raise
            time.sleep(base_delay * attempt)

def create_bedrock_client():
    try:
        import boto3
        from botocore.config import Config
    except ImportError as exc:
        raise RuntimeError("Falta dependencia boto3.") from exc

    aws_access_key = os.getenv("AWS_ACCESS_KEY_ID", "").strip()
    aws_secret_key = os.getenv("AWS_SECRET_ACCESS_KEY", "").strip()
    
    region = os.getenv("AWS_REGION", os.getenv("AWS_DEFAULT_REGION", "us-east-1"))
    # AQUI ESTÁ EL CAMBIO PARA SONNET 4.6
    model_id = os.getenv("BEDROCK_MODEL_ID", "anthropic.claude-sonnet-4-6")

    session = boto3.Session(
        aws_access_key_id=aws_access_key,
        aws_secret_access_key=aws_secret_key,
        region_name=region,
    )
    client = session.client(
        service_name="bedrock-runtime",
        region_name=region,
        config=Config(
            connect_timeout=int(os.getenv("BEDROCK_CONNECT_TIMEOUT_SECONDS", "10")),
            read_timeout=int(os.getenv("EXTRACTOR_REQUEST_TIMEOUT_SECONDS", "180")),
            retries={"max_attempts": 0} # Manejado manualmente en invoke_model_with_retry
        ),
    )
    return client, region, model_id

# ==========================================
# MOTOR DE CLASIFICACIÓN
# ==========================================
ROUTING_TABLE = {
    "PRESIDENTE":              (0, 0, "Presidente"),
    "VICEPRESIDENTE":          (0, 1, "Vicepresidente"),
    "TESORERO":                (0, 2, "Tesorero"),
    "SECRETARIO":              (0, 3, "Secretario"),
    "SUPLENTE DE PRESIDENTE":           (1, 0, "Suplente de Presidente"),
    "SUPLENTE PRESIDENTE":              (1, 0, "Suplente de Presidente"),
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
    structured_output = [
        {"bloque_num": 1, "bloque_nombre": "Bloque 1 - Directiva",                              "candidatos": []},
        {"bloque_num": 2, "bloque_nombre": "Bloque 2 - Delegados Asojuntas",                    "candidatos": []},
        {"bloque_num": 3, "bloque_nombre": "Bloque 3 - Fiscal",                                 "candidatos": []},
        {"bloque_num": 4, "bloque_nombre": "Bloque 4 - Comisión de convivencia y conciliación", "candidatos": []},
        {"bloque_num": 99,"bloque_nombre": "Cargos No Reconocidos",                             "candidatos": []},
    ]
    seen_fingerprints = set()
    for cand in raw_candidates:
        nombre = cand.get('nombre', '').strip()
        identificacion = cand.get('identificacion', '').strip()
        cargo = cand.get('cargo', '').strip()
        
        fingerprint = hashlib.md5(f"{nombre}{identificacion}{cargo}".encode()).hexdigest()
        if fingerprint in seen_fingerprints:
            continue
        seen_fingerprints.add(fingerprint)

        normalized_cargo = normalize_text_string(cargo)
        is_assigned     = False

        for key in SORTED_ROUTING_KEYS:
            normalized_key = normalize_text_string(key)
            if normalized_key == normalized_cargo or normalized_key in normalized_cargo:
                block_idx, order_idx, official_label = ROUTING_TABLE[key]
                structured_output[block_idx]["candidatos"].append({
                    "cargo":          official_label,
                    "nombre":         nombre,
                    "identificacion": identificacion,
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
    plancha_blocks = []
    elected_people = []

    for block in structured_blocks:
        block_name = str(block.get("bloque_nombre", "SIN BLOQUE")).strip() or "SIN BLOQUE"
        block_candidates = []

        for cand in block.get("candidatos", []):
            cargo = str(cand.get("cargo", "")).strip()
            nombre = str(cand.get("nombre", "")).strip()
            identificacion = str(cand.get("identificacion", "")).strip()

            if not cargo and not nombre:
                continue

            block_candidates.append({
                "puesto": cargo,
                "nombre": nombre,
                "identificacion": identificacion,
                "celular": str(cand.get("celular", "")).strip(),
                "correo": str(cand.get("correo", "")).strip(),
            })

            if nombre:
                parts = nombre.split()
                first_name = parts[0] if parts else ""
                last_name = " ".join(parts[1:]) if len(parts) > 1 else "SIN_APELLIDO"

                elected_people.append({
                    "first_name": first_name,
                    "last_name": last_name,
                    "document_number": identificacion or None,
                    "phone": cand.get("celular") or None,
                    "email": cand.get("correo") or None,
                    "notes": f"Cargo OCR: {cargo or 'SIN_CARGO'}",
                    "review_status": "pending",
                })

        if block_candidates:
            plancha_blocks.append({"titulo": f"Bloque - {block_name}", "cargos": block_candidates})

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
    try:
        aws_client, region, bedrock_model_id = create_bedrock_client()
    except RuntimeError as exc:
        return [], {"status": "EXCEPTION", "confidence_score": 0, "anomaly_flag": str(exc)}

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
        response = invoke_model_with_retry(aws_client, bedrock_model_id, payload)
        response_body = json.loads(response["body"].read())

        for content in response_body.get("content", []):
            if content.get("type") == "tool_use" and content.get("name") == "parse_electoral_document":
                parsed_data = content.get("input", {})
                candidates_list = parsed_data.get("candidatos_detectados", [])
                audit_data = parsed_data.get("auditoria", {})
                
                for c in candidates_list:
                    c["foto_origen"] = image_path.name
                    
                audit_data["status"] = "SUCCESS"
                return candidates_list, audit_data

        return [], {"status": "SCHEMA_BINDING_FAILURE", "confidence_score": 0, "anomaly_flag": "Schema missing"}

    except Exception as e:
        message = build_bedrock_error_message(e, region, bedrock_model_id)
        return [], {"status": "EXCEPTION", "confidence_score": 0, "anomaly_flag": message}

# ==========================================
# FLUJO PRINCIPAL BATCH
# ==========================================
FAILURE_STATUSES = {"EXCEPTION", "SCHEMA_BINDING_FAILURE"}

def process_single_image(image_path: str, dry_run: bool):
    image = Path(image_path).resolve()
    if not image.exists() or not image.is_file():
        sys.stdout.write(json.dumps({"error": f"Imagen invalida: {image}"}))
        sys.exit(1)

    candidates, audit_info = invoke_bedrock_parsing(image)

    if audit_info.get("status") in FAILURE_STATUSES:
        sys.stdout.write(json.dumps({
            "error": audit_info.get("anomaly_flag") or "Fallo desconocido en la extraccion OCR",
            "status": audit_info.get("status"),
            "source_file": image.name,
        }, ensure_ascii=False))
        sys.exit(1)

    structured = process_classification_engine(candidates)
    normalized_payload = normalize_plancha_payload(structured)

    if dry_run:
        sys.stdout.write(json.dumps({"normalized_payload": normalized_payload}, ensure_ascii=False, indent=2))
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
    sys.stdout.write(json.dumps(response, ensure_ascii=False))

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Extractor OCR de candidatos (planchas)")
    parser.add_argument("--image", type=str, help="Ruta de una imagen individual")
    parser.add_argument("--dry-run", action="store_true", help="Imprime solo normalized_payload")
    args = parser.parse_args()

    if args.image:
        process_single_image(args.image, args.dry_run)
        sys.exit(0)

    sys.stdout.write(json.dumps({"error": "Debes indicar --image <ruta_imagen>."}))
    sys.exit(1)