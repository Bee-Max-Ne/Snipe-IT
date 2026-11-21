# ==============================================================================
# Snipe-IT ITAM - Kịch Bản Đóng Gói Bằng Chứng Kiểm Thử Tự Động Phân Vùng 4
# Thành viên: Nguyễn Thành Minh (MSSV: 2312691)
# ==============================================================================

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$sessionDir = "storage/logs/test-runs/${timestamp}_part4"
$artifactDir = "storage/logs/artifacts"
New-Item -ItemType Directory -Force -Path $sessionDir | Out-Null
New-Item -ItemType Directory -Force -Path $artifactDir | Out-Null

Write-Host ">>> DANG CHAY KIEM THU VA THU THAP DU LIEU PHAN VUNG 4..." -ForegroundColor Cyan

# 1. Chạy test PHPUnit và xuất ra log file:
$logFile = "$sessionDir/phpunit_execution.log"
php artisan test --filter=test_tc_sec_ | Tee-Object -FilePath $logFile

# 2. Xuất lịch sử Git commit chứng minh quá trình kiểm thử:
git log --oneline -n 20 --grep="test(" | Out-File "$sessionDir/git_commit_evidence.txt" -Encoding utf8

# 3. Nén toàn bộ phiên chạy vào file zip artifact:
$zipFile = "$artifactDir/Evidence_PhanVung4_$timestamp.zip"
Compress-Archive -Path "$sessionDir/*" -DestinationPath $zipFile -Force

Write-Host ""
Write-Host ">>> DONG GOI HO SO BANG CHUNG THANH CONG TAI: $zipFile" -ForegroundColor Green
