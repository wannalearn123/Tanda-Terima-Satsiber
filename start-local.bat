@echo off
REM start-local.bat — One-click native local deployment (Windows, location-independent)
REM Runs wherever cloned (C:, D:, folder with spaces)

REM Ensure script runs from repo root
cd /d "%~dp0"

echo [*] Starting Tanda-Terima-Satsiber (local one-click)...

echo [*] Memeriksa ekstensi PHP...
call install-extensions.bat

REM 2. Setup DB if not exists
if not exist database\database.sqlite (
    echo [*] Membuat database & seed (setup.php)...
    php setup.php
) else (
    echo [✓] SQLite database sudah ada, lewati setup
)

REM 3. Start PHP server in background (relative path)
echo [*] Menjalankan PHP server di port 8090...
start "TandaTerimaServer" php -S 0.0.0.0:8090 public\router.php

REM 4. Show result
echo.
echo ✅ Tanda-Terima-Satsiber berjalan secara lokal!
echo    Local URL: http://localhost:8090
echo.
echo    Tekan Ctrl+C di jendela ini untuk menghentikan.

REM 5. Keep window open
pause
