# install-extensions.ps1 — Check PHP 8.3 extensions (Windows, any location)
# Runs wherever this repo is cloned

Set-Location -Path (Split-Path -Parent $MyInvocation.MyCommand.Definition -ErrorAction Stop) -ErrorAction Stop
Write-Host "[*] Memeriksa ekstensi PHP 8.3..." -ForegroundColor Cyan

# Find PHP ini dynamically
$iniLine = (php --ini 2>$null) | Select-String -Pattern "Loaded Configuration"
if ($iniLine) {
    $PHPINI = ($iniLine -split ":")[1].Trim()
    Write-Host "[*] PHP ini: $PHPINI" -ForegroundColor Gray
}

$ext = @("gd", "pdo_sqlite", "mbstring")
$missing = @()

foreach ($e in $ext) {
    $check = (php -m 2>$null) | Select-String -Pattern "^$e$" -Quiet
    if (-not $check) { $missing += $e }
}

if ($missing.Count -eq 0) {
    Write-Host "[✓] Semua ekstensi sudah terinstall: gd, pdo_sqlite, mbstring" -ForegroundColor Green
} else {
    Write-Host "[*] Ekstensi hilang: $($missing -join ', ')" -ForegroundColor Yellow
    Write-Host "[*] Silakan edit file ini dan aktifkan ekstensi: gd, pdo_sqlite, mbstring" -ForegroundColor Yellow
    if ($PHPINI) {
        Write-Host "[*] File php.ini lokasi: $PHPINI" -ForegroundColor Yellow
    }
}
