# proyecto-asojuntas
Sistema para la digitalización del escrutinio electoral mediante técnicas de extracción de texto (OCR) en las elecciones de la organización de acción comunal

## Ingestion OCR desde VPS

El proyecto tiene endpoints para ingestión segura del extractor:

- POST /api/ingest/scrutiny-files
- POST /api/ingest/scrutiny-extractions

Autenticacion requerida: header X-Ingest-Token.

Variables en .env:

- EXTRACTOR_INGEST_TOKEN
- EXTRACTOR_MAX_UPLOAD_KB
- AWS_ACCESS_KEY_ID
- AWS_SECRET_ACCESS_KEY
- AWS_REGION
- INGEST_API_BASE_URL

Script de integracion y prueba:

- data_extraction/motor_extraction.py
- data_extraction/README.md
