param(
    [string]$OnlyPart = ""
)

$ErrorActionPreference = "Continue"

$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if ($phpCmd) {
    $env:PHP_BINARY = $phpCmd.Source
}

$baseReportDir = "storage/logs/test-reports"
New-Item -ItemType Directory -Force -Path $baseReportDir | Out-Null

$parts = @(
    @{ Name = "part1"; Config = "phpunit.part1.xml"; Junit = "junit_part1_report.xml"; Infra = $false },
    @{ Name = "part2"; Config = "phpunit.part2.xml"; Junit = "junit_part2_report.xml"; Infra = $false },
    @{ Name = "part3"; Config = "phpunit.part3.xml"; Junit = "junit_part3_report.xml"; Infra = $true },
    @{ Name = "part4"; Config = "phpunit.part4.xml"; Junit = "junit_part4_report.xml"; Infra = $false }
)

foreach ($p in $parts) {
    $name = $p.Name
    if ($OnlyPart -ne "" -and $OnlyPart -ne $name) {
        continue
    }

    $dir = "$baseReportDir/$name"
    New-Item -ItemType Directory -Force -Path $dir | Out-Null

    Write-Host "==============================================================================" -ForegroundColor Cyan
    Write-Host " [K16 + PCOV] Dang chay $name ($($p.Config)) -> $dir" -ForegroundColor Yellow
    Write-Host "==============================================================================" -ForegroundColor Cyan

    $logFile = "$dir/phpunit_${name}_execution.log"
    $junitFile = "$dir/$($p.Junit)"
    $testdoxFile = "$dir/testdox.html"
    $cloverFile = "$dir/coverage_clover.xml"
    $covHtmlDir = "$dir/coverage-html"

    node tests/testcontainers/run.mjs -c $p.Config `
        --log-junit $junitFile `
        --testdox-html $testdoxFile `
        --coverage-clover $cloverFile `
        --coverage-html $covHtmlDir `
        --coverage-text | Tee-Object -FilePath $logFile

    if ($p.Infra) {
        npm run test:k16:infra | Tee-Object -FilePath "$dir/k16_infra_tc_inf_01.log"
    }

    if (Test-Path "tests/capture_env_metadata.php") {
        php tests/capture_env_metadata.php "$dir/environment_metadata.json"
    }
    git log --oneline -n 25 | Out-File "$dir/git_commit_evidence.txt" -Encoding utf8

    # Tinh SHA-256 cho cac tep minh chung trong thu muc phan vung
    Get-ChildItem -Path $dir -File | Where-Object { $_.Name -ne "checksums.sha256" } | Sort-Object Name | ForEach-Object {
        $hash = (Get-FileHash -Path $_.FullName -Algorithm SHA256).Hash.ToLower()
        "$hash  $($_.Name)"
    } | Out-File "$dir/checksums.sha256" -Encoding utf8
}

# Tong hop ket qua 4 phan vung tu JUnit XML & Clover XML
@'
import os, xml.etree.ElementTree as ET

base = r"storage/logs/test-reports"
parts = [
    ("part1", "PV1 — Checkout/Checkin (2312718)", "junit_part1_report.xml"),
    ("part2", "PV2 — Hardware/Depreciation (2312729)", "junit_part2_report.xml"),
    ("part3", "PV3 — License/Accessory/K16 (2314506)", "junit_part3_report.xml"),
    ("part4", "PV4 — RBAC/Policy/FMCS (2312691)", "junit_part4_report.xml"),
]

lines = [
    "# Tổng hợp Minh chứng Local 4 Phân vùng (K16 MySQL 8.0.36 + PCOV)\n",
    "| Phân vùng | Tests | Assertions | Failures | Errors | Skipped | PCOV Line Coverage |",
    "| :--- | :---: | :---: | :---: | :---: | :---: | :---: |",
]

for folder, label, junit_name in parts:
    d = os.path.join(base, folder)
    junit_path = os.path.join(d, junit_name)
    clover_path = os.path.join(d, "coverage_clover.xml")
    tests = assertions = failures = errors = skipped = "N/A"
    if os.path.exists(junit_path):
        root = ET.parse(junit_path).getroot()
        ts = root.find("testsuite") or root
        tests = ts.attrib.get("tests", "0")
        assertions = ts.attrib.get("assertions", "0")
        failures = ts.attrib.get("failures", "0")
        errors = ts.attrib.get("errors", "0")
        skipped = ts.attrib.get("skipped", "0")
    cov = "N/A"
    if os.path.exists(clover_path):
        croot = ET.parse(clover_path).getroot()
        metrics = croot.find("./project/metrics")
        if metrics is not None:
            stmts = int(metrics.attrib.get("statements", "0"))
            covered = int(metrics.attrib.get("coveredstatements", "0"))
            pct = (covered / stmts * 100.0) if stmts > 0 else 0.0
            cov = f"{pct:.2f}% ({covered}/{stmts})"
    lines.append(f"| {label} | {tests} | {assertions} | {failures} | {errors} | {skipped} | {cov} |")

out_path = os.path.join(base, "SUMMARY_ALL_4_PARTS.md")
with open(out_path, "w", encoding="utf-8") as f:
    f.write("\n".join(lines) + "\n")
print("Saved summary to:", out_path)
'@ | python -
