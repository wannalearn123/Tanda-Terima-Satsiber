@echo off
REM install-extensions.bat — Install PHP 8.3 extensions (Windows)
REM Asumsi: PHP 8.3 CLI sudah terinstall (XAMPP, WAMP, atau manual)

echo [*] Memeriksa ekstensi PHP 8.3...

set "ext=gd pdo_sqlite mbstring"
set "missing="

for %%e in (%ext%) do (
    php -m 2>nul | findstr /i "^%%e$" >nul
    if !errorlevel! neq 0 (
        set "missing=!missing! %%e"
    )
)

if "%missing%"=="" (
    echo [✓] Semua ekstensi sudah terinstall: gd, pdo_sqlite, mbstring
) else (
    echo [*] Ekstensi hilang:%missing%
    echo [*] Silakan edit php.ini untuk aktifkan ekstensi: gd, pdo_sqlite, mbstring
    echo [*] File php.ini biasanya ada di: C:\php\php.ini atau C:\祥\php\php.ini
)