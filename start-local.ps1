# start-local.ps1 — One-click local deployment (Windows)
# Usage: .\start-local.ps1

Write-Host "[*] Starting Tanda-Terima-Satsiber (local one-click)..." -ForegroundColor Cyan

# 1. Install/check ekstensi PHP
Write-Host "[*] Memeriksa ekstensi PHP..." -ForegroundColor Cyan
if (Test-Path ".\install-extensions.ps1") {
    .\install-extensions.ps1
}

# 2. Setup database jika belum ada
if (-not (Test-Path "database/database.sqlite")) {
    Write-Host "[*] Membuat database & seed (setup.php)..." -ForegroundColor Cyan
    php setup.php
} else {
    Write-Host "[✓] SQLite database sudah ada, lewati setup" -ForegroundColor Green
}

# 3. Jalankan PHP server di background
Write-Host "[*] Menjalankan PHP server di port 8090..." -ForegroundColor Cyan
Start-Process -FilePath "php" -ArgumentList "-S 0.0.0.0:8090 public/router.php" -WindowStyle Hidden -WorkingDirectory (Get-Location)

# 4. Tampilkan hasil
Write-Host ""
Write-Host "✅ Tanda-Terima-Satsiber berjalan secara lokal!" -ForegroundColor Green
Write-Host "   Local URL: http://localhost:8090" -ForegroundColor Cyan
Write-Host ""
Write-Host "   Tekan Ctrl+C untuk menghentikan semua proses" -ForegroundColor Gray

# 5. Keep running (prevent closed script)
while ($true) {
    Start-Sleep -Seconds 1
}