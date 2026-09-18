# Usa PHP 8.4 solo en la sesion actual de PowerShell (no toca el PATH del sistema).
# Uso:  . .\scripts\use-php84.ps1
$php84 = 'C:\php-8.4.4'

if (-not (Test-Path (Join-Path $php84 'php.exe'))) {
    Write-Error "No se encontro php.exe en $php84"
    return
}

$env:PATH = "$php84;" + (($env:PATH -split ';' | Where-Object { $_ -and $_ -ne $php84 }) -join ';')
Write-Host "PHP activo en esta sesion:" -ForegroundColor Cyan
php -v | Select-Object -First 1
