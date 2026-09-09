@echo off
setlocal enabledelayedexpansion
REM install-extensions.bat — Check PHP 8.3 extensions (location-independent)
REM Runs wherever this repo is cloned (C:, D:, etc.)

REM Make sure script runs from repo root
cd /d "%~dp0"

echo [*] Memeriksa ekstensi PHP 8.3...

REM Find PHP ini dynamically
for /f "tokens=2 delims==" %%a in ('php --ini 2^>nul ^| findstr /i "Loaded Configuration"') do set "PHPINI=%%a"

echo [*] PHP ini: %PHPINI%

set "missing="

REM Check gd
php -m 2>nul | findstr /i /c:"gd" >nul
if errorlevel 1 set "missing=%missing% gd"

REM Check pdo_sqlite
php -m 2>nul | findstr /i /c:"pdo_sqlite" >nul
if errorlevel 1 set "missing=%missing% pdo_sqlite"

REM Check mbstring
php -m 2>nul | findstr /i /c:"mbstring" >nul
if errorlevel 1 set "missing=%missing% mbstring"

if "%missing%"==" " (
    echo [✓] Semua ekstensi sudah terinstall: gd, pdo_sqlite, mbstring
) else (
    echo [*] Ekstensi hilang:%missing%
    echo [*] Silakan edit file ini dan aktifkan ekstensi: gd, pdo_sqlite, mbstring
    echo [*] File php.ini lokasi: %PHPINI%
)
