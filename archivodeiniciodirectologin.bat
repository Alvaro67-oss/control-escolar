@echo off

cd /d "%~dp0"

docker compose up -d

timeout /t 40 >nul

start "" "http://localhost:8080/login.php"

timeout /t 2 >nul

start "" "http://localhost:8080/qr"
