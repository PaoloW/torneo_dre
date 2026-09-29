@echo off
REM Importa los CSV del torneo (encoding CP1252, 28 o 31 columnas, lee por encabezados).
REM Uso:  importar_csv.bat            -> importa los 3 CSV sin vaciar
REM        importar_csv.bat --fresh    -> vacia participantes y solo importa DATA GRAL.MACRO
cd /d "%~dp0"
if "%~1"=="--fresh" (
    "C:\xampp\php\php.exe" artisan torneo:importar "%~dp0docs\DATA GRAL.MACRO.csv" --fresh
    "C:\xampp\php\php.exe" artisan torneo:importar "%~dp0docs\GRAL-METRICAS.csv"
    "C:\xampp\php\php.exe" artisan torneo:importar "%~dp0docs\AJEDREZ.csv"
) else (
    "C:\xampp\php\php.exe" artisan torneo:importar "%~dp0docs\DATA GRAL.MACRO.csv"
    "C:\xampp\php\php.exe" artisan torneo:importar "%~dp0docs\GRAL-METRICAS.csv"
    "C:\xampp\php\php.exe" artisan torneo:importar "%~dp0docs\AJEDREZ.csv"
)
