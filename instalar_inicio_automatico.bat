@echo off
setlocal

set "SCRIPT=%~dp0inicio_automatico.bat"
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "ACCESO=%STARTUP%\Control Escolar.lnk"

powershell -NoProfile -Command "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut('%ACCESO%'); $s.TargetPath = '%SCRIPT%'; $s.WorkingDirectory = '%~dp0'; $s.WindowStyle = 7; $s.Description = 'Inicia Control Escolar con Docker'; $s.Save()"

echo.
echo Listo. Control Escolar se iniciara al encender Windows.
echo Acceso directo creado en:
echo %ACCESO%
echo.
pause

endlocal
