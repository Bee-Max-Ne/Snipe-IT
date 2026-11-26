@echo off
REM ==============================================================================
REM Snipe-IT ITAM - Kịch Bản Chạy Kiểm Thử Tự Động Phân Vùng 4
REM Thành viên: Nguyễn Thành Minh (MSSV: 2312691)
REM Phạm vi: RBAC (User), Policies (UserPolicy), FMCS Multi-tenancy, Actionlog
REM Kỹ thuật bắt buộc: K16 Testcontainers (MySQL 8.0.36 Ephemeral Container)
REM ==============================================================================

echo [INFO] Dang khoi dong bo kiem thu Phan Vung 4 (TC-SEC-01 den TC-SEC-15)...
echo.

php artisan test --filter=test_tc_sec_

IF %ERRORLEVEL% EQU 0 (
    echo.
    echo ==============================================================================
    echo [THANH CONG] TAT CA 15 CA KIEM THU PHAN VUNG 4 DA PASS HOAN TOAN!
    echo ==============================================================================
    exit /b 0
) ELSE (
    echo.
    echo ==============================================================================
    echo [THAT BAI] CO CA KIEM THU BI LOI. VUI LONG KIEM TRA LOG CHI TIET!
    echo ==============================================================================
    exit /b 1
)
