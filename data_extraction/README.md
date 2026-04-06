# Integracion OCR -> Laravel (VPS)

Este flujo hace 3 pasos en un solo comando:

1. Lee una imagen del acta.
2. Ejecuta OCR con AWS Bedrock.
3. Envia archivo + extraccion a la API Laravel.

## Variables necesarias en .env

- AWS_ACCESS_KEY_ID
- AWS_SECRET_ACCESS_KEY
- AWS_REGION
- EXTRACTOR_INGEST_TOKEN
- INGEST_API_BASE_URL (ejemplo: http://127.0.0.1:8000/api)
- BEDROCK_MODEL_ID (opcional)

## Instalacion

Desde la raiz del proyecto:

```bash
pip install -r data_extraction/requirements.txt
```

## Prueba rapida (solo OCR + normalizacion)

```bash
python data_extraction/motor_extraction.py --record-id 1 --image storage/app/private/actas/test.jpg --dry-run
```

## Prueba completa (sube imagen + guarda extraccion)

```bash
python data_extraction/motor_extraction.py --record-id 1 --image storage/app/private/actas/test.jpg --page-number 1 --is-primary
```

## Notas de operacion

- El record_id debe existir en scrutiny_records.
- La API usa el header X-Ingest-Token.
- El extractor depende de AWS Bedrock y requiere salida a Internet, credenciales AWS validas y acceso al endpoint de la region configurada.
- Si aparece un error como `Could not connect to the endpoint URL`, revisa `AWS_REGION`, conectividad de red, proxy/VPN y permisos IAM para `bedrock:InvokeModel`.
- Si ya tienes scrutiny_record_file_id, puedes omitir upload:

```bash
python data_extraction/motor_extraction.py --record-id 1 --image storage/app/private/actas/test.jpg --scrutiny-record-file-id 123
```
