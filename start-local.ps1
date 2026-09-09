# start-local.ps1 — One-click native deployment (Windows, any location)
# Usage: .\start-local.ps1
# Works wherever this repo is cloned (C:, D:, etc.)

Write-Host "[*] Starting Tanda-Terima-Satsiber (local one-click)..." -ForegroundColor Cyan

# Set working directory to repo root (script location)
$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $repoRoot

# 1. Check extensions
Write-Host "[*] Memeriksa ekstensi PHP..." -ForegroundColor Cyan
if (Test-Path ".\install-extensions.ps1") {
    .\install-extensions.ps1
}

# 2. Setup database if not exists
if (-not (Test-Path "database\database.sqlite")) {
    Write-Host "[*] Membuat database & seed (setup.php)..." -ForegroundColor Cyan
    php setup.php
} else {
    Write-Host "[✓] SQLite database sudah ada, lewati setup" -ForegroundColor Green
}

# 3. Start PHP server (relative path, background, any drive)
Write-Host "[*] Menjalankan PHP server di port 8090..." -ForegroundColor Cyan
Start-Process -FilePath "php" -ArgumentList "-S 0.0.0.0:8090 public/router.php" -WindowStyle Hidden -WorkingDirectory $repoRoot

# 4. Show result
Write-Host ""
Write-Host "✅ Tanda-Terima-Satsiber berjalan secara lokal!" -ForegroundColor Green
Write-Host "   Local URL: http://localhost:8090" -ForegroundColor Cyan
Write-Host ""
Write-Host "   Tekan Ctrl+C untuk menghentikan semua proses" -ForegroundColor Gray

# 5. Keep running
while ($true) {
    Start-Sleep -Seconds 1
}
