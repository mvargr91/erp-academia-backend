#Requires -RunAsAdministrator
# Desarrollo local multi-academia. Ejecutar como administrador (clic derecho > "Ejecutar con PowerShell"
# desde una consola de administrador) cada vez que crees una academia nueva:
#   1. Agrega <codigo>.erp-academia.test de cada academia al archivo hosts.
#   2. Reinicia Apache de WAMP (para tomar el vhost *.erp-academia.test).
#   3. Limpia la caché DNS de Windows.

$ErrorActionPreference = 'Stop'
$backend = Split-Path -Parent $PSScriptRoot
$php = Get-ChildItem 'C:\wamp64\bin\php' -Directory | Where-Object { $_.Name -like 'php8.2*' } |
    Select-Object -Last 1 | ForEach-Object { Join-Path $_.FullName 'php.exe' }

Write-Host "1/3 Actualizando hosts..." -ForegroundColor Cyan
Push-Location $backend
& $php artisan academia:hosts
Pop-Location

Write-Host "2/3 Reiniciando Apache (wampapache64)..." -ForegroundColor Cyan
Restart-Service -Name wampapache64

Write-Host "3/3 Limpiando caché DNS..." -ForegroundColor Cyan
ipconfig /flushdns | Out-Null

Write-Host "`nListo. Con 'npm run dev' corriendo en el frontend, abre p. ej. http://demo.erp-academia.test" -ForegroundColor Green
Read-Host "Enter para cerrar"
