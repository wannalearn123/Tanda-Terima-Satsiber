# install-extensions.ps1 — Install PHP 8.3 extensions (Windows)
# Asumsi: PHP 8.3 CLI sudah terinstall (XAMPP, WAMP, atau manual)

Write-Host "[*] Memeriksa ekstensi PHP 8.3..." -ForegroundColor Cyan

$ext = @("gd", "pdo_sqlite", "mbstring")
$missing = @()

foreach ($e in $ext) {
    $check = php -m 2>$null | Select-String -Pattern "^$e$" -Quiet
    if (-not $check) { $missing += $e }
}

if ($missing.Count -eq 0) {
    Write-Host "[✓] Semua ekstensi sudah terinstall: gd, pdo_sqlite, mbstring" -ForegroundColor Green
} else {
    Write-Host "[*] Ekstensi hilang: $($missing -join ', ')" -ForegroundColor Yellow
    Write-Host "[*] Silakan edit file php.ini untuk aktifkan ekstensi: gd, pdo_sqlite, mbstring" -ForegroundColor Yellow
    Write-Host "[*] File php.ini biasanya ada di: C:\php\php.ini atau C:\xampp\php\php.ini" -ForegroundColor Yellow
}
