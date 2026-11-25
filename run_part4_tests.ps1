param(
    [switch]$FallbackLocal,
    [string]$Filter = ""
)

# ==============================================================================
# Snipe-IT ITAM - Kịch Bản Chạy Kiểm Thử & Ghi Log Tự Động Phân Vùng 4 (K16)
# Thành viên: Nguyễn Thành Minh (MSSV: 2312691)
# Phạm vi: Unit RBAC/Policy/FMCS + Feature/API Integration (115 tests / 443 assertions)
# Kỹ thuật: K16 Testcontainers (tests/testcontainers/run.mjs -> MySQL 8.0.36)
# ==============================================================================

Write-Host "==============================================================================" -ForegroundColor Cyan
Write-Host " [PHAN VUNG 4] KHOI DONG KIEM THU AN NINH & TICH HOP API (K16 TESTCONTAINERS)" -ForegroundColor Yellow
Write-Host " Sinh vien: Nguyen Thanh Minh (MSSV: 2312691) - Nhom 05" -ForegroundColor Yellow
Write-Host "==============================================================================" -ForegroundColor Cyan
Write-Host ""

$reportDir = "storage/logs/test-reports"
New-Item -ItemType Directory -Force -Path $reportDir | Out-Null
New-Item -ItemType Directory -Force -Path "storage/logs/artifacts" | Out-Null

$logFile = "$reportDir/phpunit_part4_execution.log"
$testdoxFile = "$reportDir/testdox.html"
$summaryFile = "$reportDir/SUMMARY_REPORT.md"

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
    Write-Host "hoac chay lai voi co: .\run_part4_tests.ps1 -FallbackLocal" -ForegroundColor Yellow
    exit 1
}

# Tự động kích hoạt xuất Code Coverage nếu máy có PCOV hoặc Xdebug
$hasCoverageDriver = $false
if ($phpCmd) {
    $hasCoverageDriver = (& $phpCmd.Source -m 2>$null | Select-String -Pattern "^(pcov|xdebug)$" -Quiet)
}

$phpunitArgs = @("-c", "phpunit.part4.xml", "--testdox-html", $testdoxFile)
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
    Write-Host "[1/3] Dang chay bo test Phan vung 4 qua K16 Testcontainers (MySQL 8.0.36)..." -ForegroundColor Green
    node tests/testcontainers/run.mjs @phpunitArgs | Tee-Object -FilePath $logFile
    $testExit = $LASTEXITCODE
} else {
    Write-Host "[1/3] [CANH BAO] Dang chay che do -FallbackLocal (PHPUnit cuc bo, khong co Docker MySQL)..." -ForegroundColor Yellow
    if (Test-Path "$reportDir/mysql_container_proof.json") {
        Remove-Item "$reportDir/mysql_container_proof.json" -Force
    }
    vendor/bin/phpunit @phpunitArgs | Tee-Object -FilePath $logFile
    $testExit = $LASTEXITCODE
}

Write-Host "[2/3] Dang thu thap siêu dữ liệu môi trường (environment_metadata.json)..." -ForegroundColor Green
php tests/capture_env_metadata.php "$reportDir/environment_metadata.json"

Write-Host "[3/3] Dang sinh bao cao tong hop, ma bam SHA-256 va dong goi ZIP..." -ForegroundColor Green
python tests/generate_ci_summary.py $logFile $summaryFile | Out-Null

if ($testExit -eq 0) {
    Write-Host ""
    Write-Host "==============================================================================" -ForegroundColor Green
    Write-Host " [THANH CONG] TOAN BO TEST PHAN VUNG 4 DA PASS 100%!" -ForegroundColor Green
    Write-Host " Log & XML : $reportDir/" -ForegroundColor Green
    Write-Host " Summary   : $summaryFile" -ForegroundColor Green
    Write-Host "==============================================================================" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "==============================================================================" -ForegroundColor Red
    Write-Host " [THAT BAI] CO CA KIEM THU LOI (Exit Code: $testExit)! Kiem tra: $logFile" -ForegroundColor Red
    Write-Host "==============================================================================" -ForegroundColor Red
    exit $testExit
}
