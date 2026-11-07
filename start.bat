@echo off
rem ---------------------------------------------------------------------------------------------
rem  EduKSX-Glossary lokal starten (Windows)
rem  Doppelklick oder im Projektordner: start.bat
rem  Optionen:  start.bat --pull   zuerst den neuesten Stand von GitHub holen (git pull)
rem ---------------------------------------------------------------------------------------------
setlocal
chcp 65001 >nul
cd /d "%~dp0"
title EduKSX-Glossary

echo.
echo  EduKSX-Glossary - lokaler Start
echo  ================================
echo.

rem --- Voraussetzungen ------------------------------------------------------------------------
where php >nul 2>nul || (echo  [Fehler] PHP fehlt. Bitte PHP 8.4 installieren und in PATH aufnehmen. & goto :fehler)
where composer >nul 2>nul || (echo  [Fehler] Composer fehlt. https://getcomposer.org & goto :fehler)
where npm >nul 2>nul || (echo  [Fehler] Node.js 22 fehlt. https://nodejs.org & goto :fehler)

rem --- Optional: neuesten Stand holen ---------------------------------------------------------
if /i "%~1"=="--pull" (
    echo  Neuesten Stand holen ...
    git pull || goto :fehler
)

rem --- Erster Start: alles einrichten ---------------------------------------------------------
if not exist "vendor\autoload.php" (
    echo  Erster Start: Pakete, .env, Datenbank und Build einrichten ...
    call composer setup || goto :fehler
    echo.
    echo  Jetzt den ersten Admin anlegen ^(E-Mail, Name, Passwort mit mind. 12 Zeichen^):
    php artisan glossary:admin || goto :fehler
) else (
    echo  Pakete und Datenbank auf den neuesten Stand bringen ...
    call composer install --no-interaction || goto :fehler
    if not exist "node_modules" (call npm install || goto :fehler) else (call npm install --no-audit --no-fund >nul || goto :fehler)
    if not exist "storage\app\database\glossary.sqlite" type nul > "storage\app\database\glossary.sqlite"
    php artisan migrate --force || goto :fehler
)

rem --- Starten ---------------------------------------------------------------------------------
echo.
echo  Server startet: http://localhost:8000   (Administration: /admin, Beenden: Ctrl+C)
echo.
start "" /b cmd /c "timeout /t 4 /nobreak >nul & start http://localhost:8000"
call composer dev
goto :ende

:fehler
echo.
echo  Start abgebrochen. Meldung oben pruefen.
pause
exit /b 1

:ende
endlocal
