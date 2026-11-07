@echo off
rem ---------------------------------------------------------------------------------------------
rem  EduKSX-Glossary lokal starten (Windows)
rem
rem  Doppelklick: neuesten Stand des aktuellen Branches holen, einrichten, starten.
rem  start.bat BRANCH        zuerst diesen Branch auschecken (z. B. start.bat excel-import)
rem  start.bat main          zurueck auf main
rem  start.bat --ohne-pull   nichts von GitHub holen, nur starten
rem ---------------------------------------------------------------------------------------------
setlocal

rem Beim Auschecken kann sich diese Datei aendern. Darum laeuft sie aus einer Kopie im TEMP-Ordner.
if /i not "%~1"=="__kopie" (
    copy /y "%~f0" "%TEMP%\eduksx-glossary-start.bat" >nul
    "%TEMP%\eduksx-glossary-start.bat" __kopie "%~dp0" %*
    exit /b %errorlevel%
)
set "PROJEKT=%~2"
shift
shift
set "ZIEL=%~1"

chcp 65001 >nul
cd /d "%PROJEKT%" || goto :fehler
title EduKSX-Glossary

echo.
echo  EduKSX-Glossary - lokaler Start
echo  ================================
echo.

rem --- Voraussetzungen ------------------------------------------------------------------------
where git >nul 2>nul || (echo  [Fehler] Git fehlt. https://git-scm.com & goto :fehler)
where php >nul 2>nul || (echo  [Fehler] PHP fehlt. Bitte PHP 8.4 installieren und in PATH aufnehmen. & goto :fehler)
where composer >nul 2>nul || (echo  [Fehler] Composer fehlt. https://getcomposer.org & goto :fehler)
where npm >nul 2>nul || (echo  [Fehler] Node.js 22 fehlt. https://nodejs.org & goto :fehler)

rem --- Auschecken: Branch wechseln und neuesten Stand holen ---------------------------------------
if /i "%ZIEL%"=="--ohne-pull" goto :einrichten

echo  Neuesten Stand von GitHub holen ...
git fetch --all --prune || goto :fehler

rem Lokale Aenderungen? Dann nicht wechseln, sonst gingen sie verloren.
git diff --quiet && git diff --cached --quiet
if errorlevel 1 (
    echo.
    echo  [Hinweis] Es gibt lokale Aenderungen, die noch nicht committet sind:
    git status --short
    echo  Bitte committen oder mit 'git stash' beiseitelegen. Es wird nichts ausgecheckt.
    goto :fehler
)

if not "%ZIEL%"=="" (
    echo  Branch '%ZIEL%' auschecken ...
    git checkout "%ZIEL%" || goto :fehler
)
git pull --ff-only || (echo  [Fehler] Der Branch laesst sich nicht vorwaertsspulen ^(lokal anders als auf GitHub^). & goto :fehler)

echo  Stand:
git branch --show-current
git log -1 --oneline
echo.

rem --- Einrichten ---------------------------------------------------------------------------------
:einrichten
if not exist "vendor\autoload.php" (
    echo  Erster Start: Pakete, .env, Datenbank und Build einrichten ...
    call composer setup || goto :fehler
    echo.
    echo  Jetzt den ersten Admin anlegen ^(E-Mail, Name, Passwort mit mind. 12 Zeichen^):
    php artisan glossary:admin || goto :fehler
) else (
    echo  Pakete und Datenbank auf den neuesten Stand bringen ...
    call composer install --no-interaction || goto :fehler
    call npm install --no-audit --no-fund || goto :fehler
    if not exist "storage\app\database\glossary.sqlite" type nul > "storage\app\database\glossary.sqlite"
    php artisan migrate --force || goto :fehler
    php artisan optimize:clear >nul
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
