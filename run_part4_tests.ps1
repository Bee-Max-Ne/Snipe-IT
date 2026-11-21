# ==============================================================================
# Snipe-IT ITAM - Kịch Bản Chạy Kiểm Thử & Ghi Log Tự Động Phân Vùng 4
# Thành viên: Nguyễn Thành Minh (MSSV: 2312691)
# Phạm vi: RBAC (User), Policies (UserPolicy), FMCS Multi-tenancy, Actionlog
# Kỹ thuật: K16 Testcontainers (MySQL 8.0.36 Ephemeral Container)
# ==============================================================================

Write-Host "==============================================================================" -ForegroundColor Cyan
Write-Host " [PHAN VUNG 4] KHOI DONG KIEM THU AN NINH & DA CONG TY FMCS" -ForegroundColor Yellow
Write-Host " Sinh vien: Nguyen Thanh Minh (MSSV: 2312691) - Lop: CTK47" -ForegroundColor Yellow
Write-Host " Bo test: TC-SEC-01 den TC-SEC-15 (Tong cong 20 test executions)" -ForegroundColor Yellow
Write-Host "==============================================================================" -ForegroundColor Cyan
Write-Host ""

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$sessionDir = "storage/logs/test-runs/${timestamp}_part4"
New-Item -ItemType Directory -Force -Path $sessionDir | Out-Null
$logFile = "$sessionDir/phpunit_execution.log"

Write-Host "[1/3] Dang chay PHPUnit Test Suite va ghi log tai: $logFile..." -ForegroundColor Green

php artisan test --filter=test_tc_sec_ | Tee-Object -FilePath $logFile

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "==============================================================================" -ForegroundColor Green
    Write-Host " [THANH CONG] TAT CA 15 CA KIEM THU DA PASS 100%!" -ForegroundColor Green
    Write-Host " Thoi gian ghi nhan: $timestamp" -ForegroundColor Green
    Write-Host " File log ket qua: $logFile" -ForegroundColor Green
    Write-Host "==============================================================================" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "==============================================================================" -ForegroundColor Red
    Write-Host " [THAT BAI] CO CA KIEM THU THAT BAI! Vui long kiem tra file log: $logFile" -ForegroundColor Red
    Write-Host "==============================================================================" -ForegroundColor Red
    exit 1
}
