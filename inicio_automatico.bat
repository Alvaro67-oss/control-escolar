@echo off
setlocal

cd /d "%~dp0"

:esperar_docker
docker info >nul 2>&1
if errorlevel 1 (
    timeout /t 5 >nul
    goto esperar_docker
)

docker compose up -d

timeout /t 20 >nul
start "" "http://localhost:8080"
timeout /t 2 >nul
start "" "http://localhost:8080/qr"

endlocal
