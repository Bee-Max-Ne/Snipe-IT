param(
    [switch]$FallbackLocal,
    [string]$Filter = ""
)

# ==============================================================================
# Snipe-IT ITAM - Kịch Bản Chạy Kiểm Thử & Ghi Log Tự Động Phân Vùng 1 (K16)
# Thành viên: Phạm Nguyễn Ngọc Phước (MSSV: 2312718) - Lead QA
# Phạm vi: API Checkout, Checkin & Concurrency Race Guard (TC-CO-01..10, TC-CI-01..12, TC-RC-01)
# Kỹ thuật: K16 Testcontainers (tests/testcontainers/run.mjs -> MySQL 8.0.36)
# ==============================================================================

Write-Host "==============================================================================" -ForegroundColor Cyan
Write-Host " [PHAN VUNG 1] KHOI DONG KIEM THU API CHECKOUT/CHECKIN (K16 TESTCONTAINERS)" -ForegroundColor Yellow
Write-Host " Sinh vien: Pham Nguyen Ngoc Phuoc (MSSV: 2312718) - Lead QA - Nhom 05" -ForegroundColor Yellow
Write-Host "==============================================================================" -ForegroundColor Cyan
Write-Host ""

$reportDir = "storage/logs/test-reports"
New-Item -ItemType Directory -Force -Path $reportDir | Out-Null
New-Item -ItemType Directory -Force -Path "storage/logs/artifacts" | Out-Null

$logFile = "$reportDir/phpunit_part1_execution.log"
$testdoxFile = "$reportDir/testdox.html"
$summaryFile = "$reportDir/SUMMARY_REPORT_PART1.md"

# Tự động nhận diện PHP CLI hiện tại cho tests/testcontainers/run.mjs
$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if ($phpCmd) {
    $env:PHP_BINARY = $phpCmd.Source
}

# Kiểm tra Docker daemon trước khi kích hoạt K16 Testcontainers
$dockerReady = $false
try {
    docker info *> $null
    if ($LASTEXITCODE -eq 0) {
        $dockerReady = $true
    }
} catch {
    $dockerReady = $false
}

if (-not $dockerReady -and -not $FallbackLocal) {
    Write-Host "[LOI K16] Docker Desktop chua san sang (docker info that bai)." -ForegroundColor Red
    Write-Host "Vui long khoi dong Docker Desktop de chay dung MySQL 8.0.36 Testcontainers," -ForegroundColor Yellow
    Write-Host "hoac chay lai voi co: .\run_part1_tests.ps1 -FallbackLocal" -ForegroundColor Yellow
    exit 1
}

# Tự động kích hoạt xuất Code Coverage nếu máy có PCOV hoặc Xdebug
$hasCoverageDriver = $false
if ($phpCmd) {
    $hasCoverageDriver = (& $phpCmd.Source -m 2>$null | Select-String -Pattern "^(pcov|xdebug)$" -Quiet)
}

$phpunitArgs = @("-c", "phpunit.part1.xml", "--testdox-html", $testdoxFile)
if ($hasCoverageDriver) {
    Write-Host "[INFO] Da phat hien driver Code Coverage (PCOV/Xdebug) -> Tu dong xuat HTML & Clover XML." -ForegroundColor Cyan
    $phpunitArgs += @(
        "--coverage-html", "$reportDir/coverage-html",
        "--coverage-clover", "$reportDir/coverage_clover.xml",
        "--coverage-text"
    )
}
if ($Filter -ne "") {
    $phpunitArgs += @("--filter", $Filter)
}

if ($dockerReady) {
    Write-Host "[1/3] Dang chay bo test Phan vung 1 qua K16 Testcontainers (MySQL 8.0.36)..." -ForegroundColor Green
    node tests/testcontainers/run.mjs @phpunitArgs | Tee-Object -FilePath $logFile
    $testExit = $LASTEXITCODE
} else {
    Write-Host "[1/3] [CANH BAO] Dang chay che do -FallbackLocal (PHPUnit cuc bo, khong co Docker MySQL)..." -ForegroundColor Yellow
    vendor/bin/phpunit @phpunitArgs | Tee-Object -FilePath $logFile
    $testExit = $LASTEXITCODE
}

Write-Host "[2/3] Dang thu thap siêu dữ liệu môi trường (environment_metadata.json)..." -ForegroundColor Green
if (Test-Path "tests/capture_env_metadata.php") {
    php tests/capture_env_metadata.php "$reportDir/environment_metadata.json"
}

Write-Host "[3/3] Dang sinh lich su commit git..." -ForegroundColor Green
git log --oneline -n 25 | Out-File "$reportDir/git_commit_evidence.txt" -Encoding utf8

if ($testExit -eq 0) {
    Write-Host ""
    Write-Host "==============================================================================" -ForegroundColor Green
    Write-Host " [THANH CONG] TOAN BO TEST PHAN VUNG 1 DA HOAN TAT!" -ForegroundColor Green
    Write-Host " Log & XML : $reportDir/" -ForegroundColor Green
    Write-Host "==============================================================================" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "==============================================================================" -ForegroundColor Red
    Write-Host " [THAT BAI] CO CA KIEM THU LOI (Exit Code: $testExit)! Kiem tra: $logFile" -ForegroundColor Red
    Write-Host "==============================================================================" -ForegroundColor Red
}

exit $testExit
