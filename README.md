# proyecto-asojuntas
Sistema para la digitalización del escrutinio electoral mediante técnicas de extracción de texto (OCR) en las elecciones de la organización de acción comunal

## Prueba local (SQLite)

Para probar la aplicación en tu PC sin instalar PostgreSQL ni depender del servidor remoto.

### Requisitos

- PHP 8.4 con las extensiones `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `intl` y `gd` (revisa con `php -m`).
- Composer 2 y Node.js (versión LTS).

### Primera vez

```bash
composer install
npm install
cp .env.example .env        # en Windows: copy .env.example .env
php artisan key:generate
```

En el `.env`, deja la base de datos así (sin `DB_HOST`, `DB_DATABASE`, etc.; si vienes del servidor remoto, coméntalas con `#`):

```dotenv
DB_CONNECTION=sqlite

# Contraseña inicial de los usuarios base (superadmin, electoraladmin, jurado)
SEED_USER_PASSWORD=una-clave-de-al-menos-8

# En local las actas se guardan en storage/app (no en el SFTP del VPS)
FILESYSTEM_DISK=local
EXTRACTOR_STORAGE_DISK=local

# Opcional: Debugbar agrega una petición extra por cada llamada a la API
DEBUGBAR_ENABLED=false
```

Crea la base de datos con los catálogos, los 117 barrios, el mapa y los usuarios base:

```bash
composer run setup:local
```

> `setup:local` siempre usa SQLite (`--database=sqlite`): aunque el `.env` apunte al servidor, no lo modifica.

### Iniciar la prueba

```bash
composer run dev
```

Abre <http://localhost:8000> e ingresa con `superadmin` (o `electoraladmin`) y la contraseña de `SEED_USER_PASSWORD`.

### Tareas comunes

| Qué | Comando |
|---|---|
| Empezar de cero (borra los datos locales) | `php artisan migrate:fresh --database=sqlite --seed` |
| Aplicar migraciones nuevas | `php artisan migrate` |
| Correr las pruebas automáticas (SQLite en memoria, no toca tu BD) | `php artisan test` |
| Volver al servidor remoto | restaurar en el `.env` las líneas `DB_*` del servidor |

### Qué no funciona en local

- **Lectura OCR de actas y planchas**: necesita el extractor en Python y las credenciales de AWS; sin ellas las pantallas de escaneo no pueden procesar imágenes.
- **Imágenes de actas del servidor**: si la base local es una copia del servidor, las imágenes siguen en el SFTP del VPS y la revisión de actas muestra "No se pudo cargar la imagen del acta" (los votos sí se pueden revisar).
- La base `database/database.sqlite` está en `.gitignore`: nunca se sube al repositorio.

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
