@echo off
REM start-local.bat — One-click local deployment (Windows)
REM Usage: double-click start-local.bat atau jalankan di CMD

echo [*] Starting Tanda-Terima-Satsiber (local one-click)...

REM 1. Check/install PHP extensions
echo [*] Memeriksa ekstensi PHP...
call install-extensions.bat

REM 2. Setup database jika belum ada
if not exist database\database.sqlite (
    echo [*] Membuat database & seed (setup.php)...
    php setup.php
) else (
    echo [✓] SQLite database sudah ada, lewati setup
)

REM 3. Jalankan PHP server di background
echo [*] Menjalankan PHP server di port 8090...
start "" php -S 0.0.0.0:8090 public/router.php

REM 4. Tampilkan hasil
echo.
echo ✅ Tanda-Terima-Satsiber berjalan secara lokal!
echo    Local URL: http://localhost:8090
echo.
echo    Tekan Ctrl+C di jendela ini untuk menghentikan.

REM 5. Keep window open
pause
