import argparse
import base64
import json
import mimetypes
import os
import re
import sys
import time
import unicodedata
from pathlib import Path

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


def safe_int(value):
    if value is None:
        return 0
    text = str(value).strip()
    if text == "":
        return 0
    digits = re.sub(r"[^\d]", "", text)
    return int(digits) if digits else 0


def normalize_key(key: str) -> str:
    text = unicodedata.normalize("NFD", str(key))
    text = "".join(ch for ch in text if unicodedata.category(ch) != "Mn")
    text = text.lower()
    text = re.sub(r"[^a-z0-9]", "", text)
    return text


def get_first_value_loose(source: dict, aliases: list[str]):
    if not isinstance(source, dict):
        return None

    normalized_source = {normalize_key(key): value for key, value in source.items()}
    for alias in aliases:
        alias_norm = normalize_key(alias)
        if alias_norm in normalized_source:
            return normalized_source[alias_norm]

    return None


def get_blocks_from_payload(extracted_json: dict) -> list[dict]:
    if not isinstance(extracted_json, dict):
        return []

    direct_blocks = extracted_json.get("BLOQUES")
    if isinstance(direct_blocks, list):
        return [row for row in direct_blocks if isinstance(row, dict)]

    for key in ("bloques", "blocks", "BLOCKS"):
        candidate = extracted_json.get(key)
        if isinstance(candidate, list):
            return [row for row in candidate if isinstance(row, dict)]

    collected = []
    for key, value in extracted_json.items():
        if not isinstance(value, dict):
            continue

        key_norm = normalize_key(key)
        if "bloque" not in key_norm and "block" not in key_norm:
            continue

        row = dict(value)
        row.setdefault("BLOQUE", str(key))
        collected.append(row)

    return collected


def get_first_value(source: dict, keys: list[str]):
    for key in keys:
        if key in source:
            return source.get(key)
    return None


def parse_json_from_response(raw_text: str):
    tagged = re.search(r"<json>(.*?)</json>", raw_text, re.DOTALL | re.IGNORECASE)
    if tagged:
        return json.loads(tagged.group(1).strip())

    fallback = re.search(r"(\{.*\})", raw_text, re.DOTALL)
    if fallback:
        return json.loads(fallback.group(1).strip())

    raise ValueError("No se pudo extraer JSON desde la respuesta del modelo")


def infer_media_type(image_path: Path) -> str:
    guessed, _ = mimetypes.guess_type(str(image_path))
    if guessed:
        return guessed
    return "image/jpeg"


def build_bedrock_error_message(exc: Exception, region: str, model_id: str) -> str:
    error_text = str(exc).strip()

    if exc.__class__.__name__ == "EndpointConnectionError" or "Could not connect to the endpoint URL" in error_text:
        return (
            "No se pudo conectar a AWS Bedrock en la region "
            f"{region} usando el modelo {model_id}. Verifica que haya salida a Internet, "
            "que la region sea correcta y que el equipo tenga acceso de red al endpoint de AWS."
        )

    if exc.__class__.__name__ == "ClientError":
        response = getattr(exc, "response", {}) or {}
        error_data = response.get("Error", {}) if isinstance(response, dict) else {}
        code = str(error_data.get("Code", "")).strip()
        message = str(error_data.get("Message", error_text)).strip()

        if code in {"AccessDeniedException", "UnrecognizedClientException"}:
            return (
                "Las credenciales AWS no tienen permiso para invocar Bedrock o son invalidas. "
                "Verifica que la key pertenezca a la misma cuenta que tiene acceso al modelo y que "
                "tenga permisos bedrock:InvokeModel."
            )

        if code == "ValidationException":
            return (
                "Bedrock rechazo la solicitud por validacion. "
                f"Modelo: {model_id}. Region: {region}. Detalle: {message}"
            )

        if code == "ResourceNotFoundException":
            return (
                "No se encontro el modelo o el recurso en la region indicada. "
                f"Modelo: {model_id}. Region: {region}. Detalle: {message}"
            )

        if code == "ModelNotReadyException":
            return (
                "El modelo de Bedrock aun no esta listo o no tiene acceso habilitado en esta cuenta. "
                f"Modelo: {model_id}. Region: {region}. Detalle: {message}"
            )

        return f"Bedrock rechazo la solicitud ({code or 'ClientError'}): {message}"

    if exc.__class__.__name__ in {"NoCredentialsError", "PartialCredentialsError"}:
        return (
            "Faltan credenciales AWS validas para consultar Bedrock. "
            "Revisa AWS_ACCESS_KEY_ID y AWS_SECRET_ACCESS_KEY en el .env."
        )

    return error_text


def is_retryable_bedrock_error(exc: Exception) -> bool:
    if exc.__class__.__name__ in {"EndpointConnectionError", "ConnectTimeoutError", "ReadTimeoutError"}:
        return True

    if exc.__class__.__name__ == "ClientError":
        response = getattr(exc, "response", {}) or {}
        error_data = response.get("Error", {}) if isinstance(response, dict) else {}
        code = str(error_data.get("Code", "")).strip()

        return code in {
            "InternalServerException",
            "ModelTimeoutException",
            "ServiceUnavailableException",
            "ThrottlingException",
            "TooManyRequestsException",
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


def extract_with_bedrock(image_path: Path):
    try:
        import boto3
        from botocore.config import Config
        from botocore.exceptions import ClientError, ConnectTimeoutError, EndpointConnectionError, NoCredentialsError, PartialCredentialsError, ProxyConnectionError, ReadTimeoutError, SSLError
    except ImportError as exc:
        raise RuntimeError("Falta dependencia boto3. Instala requirements con: pip install boto3") from exc

    aws_access_key = os.getenv("AWS_ACCESS_KEY_ID", "").strip()
    aws_secret_key = os.getenv("AWS_SECRET_ACCESS_KEY", "").strip()
    aws_session_token = os.getenv("AWS_SESSION_TOKEN", "").strip()

    if not aws_access_key or not aws_secret_key:
        raise RuntimeError("Faltan credenciales AWS (AWS_ACCESS_KEY_ID/AWS_SECRET_ACCESS_KEY) en .env")

    if aws_access_key.upper().startswith("TU_") or aws_secret_key.upper().startswith("TU_"):
        raise RuntimeError("Credenciales AWS de ejemplo detectadas en .env. Configura claves reales con acceso a Bedrock")

    region = os.getenv("AWS_REGION", "us-east-1")
    model_id = os.getenv("BEDROCK_MODEL_ID", "anthropic.claude-3-5-sonnet-20240620-v1:0")

    session = boto3.Session(
        aws_access_key_id=aws_access_key,
        aws_secret_access_key=aws_secret_key,
        aws_session_token=aws_session_token or None,
        region_name=region,
    )

    client = session.client(
        service_name="bedrock-runtime",
        region_name=region,
        config=Config(
            connect_timeout=int(os.getenv("BEDROCK_CONNECT_TIMEOUT_SECONDS", "10")),
            read_timeout=int(os.getenv("BEDROCK_READ_TIMEOUT_SECONDS", "180")),
            retries={
                "max_attempts": max(1, int(os.getenv("BEDROCK_MAX_RETRIES", "3"))),
                "mode": "standard",
            },
        ),
    )

    prompt_text = """
ERES UN ANALISTA DE DATOS ELECTORALES.
Devuelve exclusivamente JSON dentro de etiquetas <json></json>.

Reglas:
1. Extrae solo la TABLA DE VOTOS de los BLOQUES 1, 2, 3 y 4.
2. Ignora totalmente la parte inferior de personas/candidatos (nombre, identificacion, celular, correo, firma).
3. Deben salir exactamente 4 bloques en el arreglo BLOQUES.
4. ATENCIÓN: Los números de los votos están ESCRITOS A MANO. Fíjate meticulosamente en los trazos para no confundir números.
5. Para cada bloque, captura exactamente estos campos numéricos:
     - TOTAL_VOTOS
     - PLANCHA_1
     - PLANCHA_2
     - PLANCHA_3
     - VOTOS_BLANCOS
     - VOTOS_NULOS
     - VOTOS_NO_MARCADOS
     - VOTOS_VALIDOS
6. Si un campo viene vacío o ilegible, usa 0.
7. Transcribe el nombre del BLOQUE como aparece en la cabecera azul.
8. Si no ves número en PLANCHA_2 o PLANCHA_3, registra 0.
9. No agregues explicaciones ni texto fuera del JSON.

Formato esperado:
{
    "BLOQUES": [
        {
            "BLOQUE": "BLOQUE N.° 1 – DIRECTIVA",
            "TOTAL_VOTOS": 165,
            "PLANCHA_1": 75,
            "PLANCHA_2": 50,
            "PLANCHA_3": 25,
            "VOTOS_BLANCOS": 10,
            "VOTOS_NULOS": 3,
            "VOTOS_NO_MARCADOS": 2,
            "VOTOS_VALIDOS": 160
        }
    ]
}
""".strip()

    with image_path.open("rb") as image_file:
        image_b64 = base64.b64encode(image_file.read()).decode("utf-8")

    body = {
        "anthropic_version": "bedrock-2023-05-31",
        "max_tokens": 4096,
        "temperature": 0.0,
        "messages": [
            {
                "role": "user",
                "content": [
                    {
                        "type": "image",
                        "source": {
                            "type": "base64",
                            "media_type": infer_media_type(image_path),
                            "data": image_b64,
                        },
                    },
                    {"type": "text", "text": prompt_text},
                ],
            }
        ],
    }

    try:
        response = invoke_model_with_retry(client, model_id, body)
    except (ProxyConnectionError, SSLError, EndpointConnectionError, ConnectTimeoutError, ReadTimeoutError, NoCredentialsError, PartialCredentialsError, ClientError) as exc:
        raise RuntimeError(build_bedrock_error_message(exc, region, model_id)) from exc

    raw_text = json.loads(response["body"].read())["content"][0]["text"]
    parsed_json = parse_json_from_response(raw_text)

    return {
        "model_id": model_id,
        "raw_text": raw_text,
        "parsed_json": parsed_json,
    }


def get_block_name(block_label: str) -> str:
    parts = re.split(r'\s*[-–—]\s*', block_label, maxsplit=1)
    if len(parts) > 1:
        return parts[1].strip()
    return block_label.strip()


def normalize_payload(extracted_json: dict):
    block_results = []
    block_votes = []
    blocks = get_blocks_from_payload(extracted_json)

    for block in blocks:
        if not isinstance(block, dict):
            continue

        block_label = str(block.get("BLOQUE", "")).strip()
        block_name = get_block_name(block_label)

        total_votes = safe_int(get_first_value_loose(block, [
            "totalvotos", "total", "votostotales", "totaldevotos"
        ]))
        plancha_1 = safe_int(get_first_value_loose(block, [
            "plancha1", "p1", "votosplancha1"
        ]))
        plancha_2 = safe_int(get_first_value_loose(block, [
            "plancha2", "p2", "votosplancha2"
        ]))
        plancha_3 = safe_int(get_first_value_loose(block, [
            "plancha3", "p3", "votosplancha3"
        ]))
        blancos = safe_int(get_first_value_loose(block, [
            "votosblancos", "blancos", "votoblanco"
        ]))
        nulos = safe_int(get_first_value_loose(block, [
            "votosnulos", "nulos", "votonulo"
        ]))
        no_marcados = safe_int(get_first_value_loose(block, [
            "votosnomarcados", "nomarcados", "votosnoMarcados"
        ]))
        validos = safe_int(get_first_value_loose(block, [
            "votosvalidos", "validos", "votovalido"
        ]))

        if validos == 0:
            inferred_valid = plancha_1 + plancha_2 + plancha_3 + blancos
            if inferred_valid > 0:
                validos = inferred_valid

        if total_votes == 0 and (validos > 0 or nulos > 0 or no_marcados > 0):
            total_votes = validos + nulos + no_marcados

        block_votes.append(
            {
                "block_name": block_name or block_label or "SIN BLOQUE",
                "total_votes": total_votes,
                "plancha_1": plancha_1,
                "plancha_2": plancha_2,
                "plancha_3": plancha_3,
                "blancos": blancos,
                "nulos": nulos,
                "no_marcados": no_marcados,
                "validos": validos,
            }
        )

        for slate_number, slate_votes in (("1", plancha_1), ("2", plancha_2), ("3", plancha_3)):
            result = {
                "votes": slate_votes,
                "status": "pending",
                "notes": f"OCR campo PLANCHA_{slate_number}",
                "block_name": block_name or block_label or "SIN BLOQUE",
                "slate_code": slate_number,
                "slate_name": f"PLANCHA {slate_number}",
            }
            block_results.append(result)

    expected_block_names = [
        "DIRECTIVA",
        "DELEGADOS ASOJUNTAS",
        "FISCAL",
        "COMISION DE CONVIVENCIA Y CONCILIACION",
    ]

    normalized_present = {
        normalize_key(row.get("block_name", ""))
        for row in block_votes
        if isinstance(row, dict)
    }

    for expected in expected_block_names:
        if normalize_key(expected) in normalized_present:
            continue

        block_votes.append(
            {
                "block_name": expected,
                "total_votes": 0,
                "plancha_1": 0,
                "plancha_2": 0,
                "plancha_3": 0,
                "blancos": 0,
                "nulos": 0,
                "no_marcados": 0,
                "validos": 0,
            }
        )

        for slate_number in ("1", "2", "3"):
            block_results.append(
                {
                    "votes": 0,
                    "status": "pending",
                    "notes": f"OCR fallback PLANCHA_{slate_number}",
                    "block_name": expected,
                    "slate_code": slate_number,
                    "slate_name": f"PLANCHA {slate_number}",
                }
            )

    return {
        "block_results": block_results,
        "block_votes": block_votes,
        "elected_people": [],
    }


def upload_scrutiny_file(api_base: str, token: str, record_id: int, image_path: Path, page_number: int, is_primary: bool, timeout: int):
    import requests

    url = f"{api_base.rstrip('/')}/ingest/scrutiny-files"
    headers = {"X-Ingest-Token": token}
    data = {
        "scrutiny_record_id": str(record_id),
        "page_number": str(page_number),
        "is_primary": "1" if is_primary else "0",
    }

    with image_path.open("rb") as f:
        files = {
            "document_file": (
                image_path.name,
                f,
                infer_media_type(image_path),
            )
        }
        response = requests.post(url, headers=headers, data=data, files=files, timeout=timeout)

    response.raise_for_status()
    payload = response.json()
    return int(payload["data"]["scrutiny_record_file_id"])


def ingest_extraction(
    api_base: str,
    token: str,
    record_id: int,
    record_file_id: int,
    model_id: str,
    confidence_score: float,
    raw_payload: dict,
    normalized_payload: dict,
    timeout: int,
):
    import requests

    url = f"{api_base.rstrip('/')}/ingest/scrutiny-extractions"
    headers = {
        "X-Ingest-Token": token,
        "Content-Type": "application/json",
    }

    body = {
        "scrutiny_record_id": record_id,
        "scrutiny_record_file_id": record_file_id,
        "source_type": "ai",
        "engine_name": "AWS-Bedrock",
        "engine_version": model_id,
        "confidence_score": confidence_score,
        "raw_payload": raw_payload,
        "normalized_payload": normalized_payload,
        "status": "pending_review",
        "notes": "Ingestion automatica desde motor_extraction.py",
    }

    response = requests.post(url, headers=headers, data=json.dumps(body), timeout=timeout)
    response.raise_for_status()
    return response.json()


def main():
    load_project_env()

    parser = argparse.ArgumentParser(description="Extrae texto con Bedrock y persiste en API Laravel")
    parser.add_argument("--record-id", type=int, required=False, default=0, help="ID de scrutiny_records")
    parser.add_argument("--image", type=str, required=True, help="Ruta local de la imagen")
    parser.add_argument("--page-number", type=int, default=1)
    parser.add_argument("--is-primary", action="store_true")
    parser.add_argument("--api-base", type=str, default=os.getenv("INGEST_API_BASE_URL", "http://127.0.0.1:8000/api"))
    parser.add_argument("--token", type=str, default=os.getenv("EXTRACTOR_INGEST_TOKEN", ""))
    parser.add_argument("--timeout", type=int, default=90)
    parser.add_argument("--confidence", type=float, default=0.85)
    parser.add_argument("--scrutiny-record-file-id", type=int, default=0, help="Usa un archivo ya subido y omite upload")
    parser.add_argument("--dry-run", action="store_true", help="No envia a API; solo imprime JSON normalizado")

    args = parser.parse_args()

    image_path = Path(args.image).resolve()
    if not image_path.exists():
        raise FileNotFoundError(f"No existe la imagen: {image_path}")

    if not args.dry_run and (not args.record_id or args.record_id < 1):
        raise RuntimeError("Debes enviar --record-id cuando no usas --dry-run")

    if not args.dry_run and not args.token:
        raise RuntimeError("Falta token. Configura EXTRACTOR_INGEST_TOKEN o usa --token")

    # Ejecución sin impresiones en consola estándar
    extraction = extract_with_bedrock(image_path)
    normalized = normalize_payload(extraction["parsed_json"])

    output_payload = {
        "status": "success",
        "mode": "dry-run" if args.dry_run else "live",
        "bedrock_extraction": extraction["parsed_json"],
        # Keep both keys for backward compatibility across callers.
        "normalized_data": normalized,
        "normalized_payload": normalized,
    }

    if args.dry_run:
        print(json.dumps(output_payload, indent=2, ensure_ascii=False))
        return

    record_file_id = args.scrutiny_record_file_id
    if record_file_id <= 0:
        record_file_id = upload_scrutiny_file(
            api_base=args.api_base,
            token=args.token,
            record_id=args.record_id,
            image_path=image_path,
            page_number=args.page_number,
            is_primary=args.is_primary,
            timeout=args.timeout,
        )

    api_result = ingest_extraction(
        api_base=args.api_base,
        token=args.token,
        record_id=args.record_id,
        record_file_id=record_file_id,
        model_id=extraction["model_id"],
        confidence_score=args.confidence,
        raw_payload=extraction["parsed_json"],
        normalized_payload=normalized,
        timeout=args.timeout,
    )

    output_payload["api_response"] = api_result
    
    # Única impresión a la salida estándar
    print(json.dumps(output_payload, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        if exc.__class__.__name__ == "HTTPError" and getattr(exc, "response", None) is not None:
            response = exc.response
            status = response.status_code
            body = response.text
            sys.stderr.write(json.dumps({"error": "http_error", "status": status, "detail": body}, ensure_ascii=False))
            sys.exit(1)

        sys.stderr.write(json.dumps({"error": str(exc)}, ensure_ascii=False))
        sys.exit(1)