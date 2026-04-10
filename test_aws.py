import os
import sys
import subprocess

# 1. Autoinstalador: Verifica e instala boto3 y python-dotenv
try:
    import boto3
    from dotenv import load_dotenv
    from botocore.exceptions import NoCredentialsError, PartialCredentialsError, ClientError
except ImportError:
    print("⚠️ Faltan librerías. Instalando 'boto3' y 'python-dotenv' automáticamente...")
    subprocess.check_call([sys.executable, "-m", "pip", "install", "boto3", "python-dotenv"])
    import boto3
    from dotenv import load_dotenv
    from botocore.exceptions import NoCredentialsError, PartialCredentialsError, ClientError
    print("✅ Librerías instaladas correctamente.\n")

def test_aws_connection():
    print("=" * 50)
    print("   PRUEBA DE CONEXIÓN A AWS (DESDE .ENV)   ")
    print("=" * 50)

    # 2. Cargar variables desde el archivo .env
    # Esto busca automáticamente un archivo llamado '.env' en el directorio actual
    load_dotenv()

    # Verificación rápida de que el .env cargó algo (sin imprimir los valores reales por seguridad)
    if not os.environ.get('AWS_ACCESS_KEY_ID') or not os.environ.get('AWS_SECRET_ACCESS_KEY'):
        print("\n❌ [ERROR] No se encontraron las credenciales en el archivo .env.")
        print("Asegúrate de tener estas líneas en tu archivo:")
        print("AWS_ACCESS_KEY_ID=tu_access_key")
        print("AWS_SECRET_ACCESS_KEY=tu_secret_key")
        return

    print("\nCredenciales detectadas. Iniciando prueba de conexión con AWS...")

    # 3. Prueba de conexión
    try:
        client = boto3.client('sts', region_name=os.environ.get('AWS_DEFAULT_REGION', 'us-east-1'))
        response = client.get_caller_identity()

        print("\n✅ [ÉXITO] Conexión establecida correctamente.")
        print("-" * 50)
        print(f"ID de Cuenta : {response.get('Account')}")
        print(f"Usuario ARN  : {response.get('Arn')}")
        print(f"ID de Usuario: {response.get('UserId')}")
        print("-" * 50)
        print("Tu archivo .env funciona perfecto y tienes comunicación con AWS.")

    except NoCredentialsError:
        print("\n❌ [ERROR] Boto3 no pudo leer las credenciales.")
    except PartialCredentialsError:
        print("\n❌ [ERROR] Las credenciales en tu .env están incompletas (falta el Access Key o el Secret Key).")
    except ClientError as e:
        error_code = e.response['Error']['Code']
        error_message = e.response['Error']['Message']
        print(f"\n❌ [ERROR DE AWS] Código: {error_code}")
        print(f"Detalle: {error_message}")
        print("Las credenciales fueron leídas de tu .env, pero AWS las rechazó (pueden estar inactivas o mal escritas).")
    except Exception as e:
        print(f"\n❌ [ERROR INESPERADO] {str(e)}")

if __name__ == "__main__":
    test_aws_connection()
