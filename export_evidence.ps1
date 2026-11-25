param(
    [switch]$FallbackLocal,
    [string]$Filter = ""
)

# ==============================================================================
# Snipe-IT ITAM - Đóng Gói Hồ Sơ Bằng Chứng Kiểm Thử Chuẩn Vàng Phân Vùng 4
# Thành viên: Nguyễn Thành Minh (MSSV: 2312691)
# ==============================================================================

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$reportDir = "storage/logs/test-reports"
$artifactDir = "storage/logs/artifacts"
New-Item -ItemType Directory -Force -Path $reportDir | Out-Null
New-Item -ItemType Directory -Force -Path $artifactDir | Out-Null

Write-Host ">>> [1/3] CHAY BO KIEM THU PHAN VUNG 4 & THU THAP LOG..." -ForegroundColor Cyan

# Lưu lịch sử commit Git để đưa vào checksums & gói ZIP
git log --oneline -n 25 | Out-File "$reportDir/git_commit_evidence.txt" -Encoding utf8

# Gọi kịch bản chuẩn run_part4_tests.ps1
& "$PSScriptRoot/run_part4_tests.ps1" -FallbackLocal:$FallbackLocal -Filter $Filter
$runExit = $LASTEXITCODE

# Sao chép gói ZIP chuẩn thành bản có dấu thời gian phục vụ lưu trữ lịch sử
$latestZip = "$artifactDir/Evidence_PhanVung4_v1.0.zip"
$stampedZip = "$artifactDir/Evidence_PhanVung4_$timestamp.zip"
if (Test-Path $latestZip) {
    Copy-Item $latestZip $stampedZip -Force
    Write-Host ""
    Write-Host ">>> DONG GOI HO SO BANG CHUNG THANH CONG TAI:" -ForegroundColor Green
    Write-Host "    - Ban chuan moi nhat : $latestZip" -ForegroundColor Green
    Write-Host "    - Ban luu dau thoi gian: $stampedZip" -ForegroundColor Green
}

exit $runExit
