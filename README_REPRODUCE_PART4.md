# HƯỚNG DẪN TÁI LẬP TOÀN BỘ BỘ KIỂM THỬ PHÂN VÙNG 4 (REPRODUCIBILITY GUIDE)

**Đề tài R03:** Hệ thống Quản lý Tài sản Công nghệ Thông tin Snipe-IT ITAM  
**Học phần:** Kiểm thử phần mềm — Trường Đại học Đà Lạt  
**Giảng viên hướng dẫn:** ThS. Nguyễn Thế Lâm  
**Sinh viên thực hiện:** Nguyễn Thành Minh (MSSV: `2312691`) — Thành viên 4  
**Phân vùng trách nhiệm:** **Phân vùng 4 — Module Người dùng (`Users`), Phân quyền RBAC (`User::hasAccess()`), Chính sách An ninh (`UserPolicy`), Cô lập Đa công ty FMCS (`CompanyableScope`), và Nhật ký kiểm toán (`Actionlog`)**  
**Kỹ thuật bắt buộc:** **K16 Testcontainers (MySQL 8.0.36 Ephemeral Container)**

---

## 1. YÊU CẦU MÔI TRƯỜNG TIỀN ĐIỀU KIỆN (PREREQUISITES)

Để tái lập thành công 100% các ca kiểm thử trên máy tính mới hoặc môi trường ảo hóa:
- **Hệ điều hành:** Windows 10/11, macOS, hoặc Linux (Ubuntu 22.04 LTS).
- **PHP:** Phiên bản `>= 8.2` (Khuyến nghị PHP 8.2 hoặc 8.3).
  - Các extension bắt buộc: `curl`, `exif`, `fileinfo`, `gd`, `mbstring`, `pdo_mysql`, `sodium`, `zip`, `pcov` (cho coverage).
- **Composer:** Phiên bản `2.x`.
- **Node.js & NPM:** Node.js `>= 18.0` (Khuyến nghị Node 20 hoặc 22 LTS).
- **Docker Engine / Docker Desktop:** Cần thiết khi kích hoạt kỹ thuật **K16 Testcontainers** (khởi chạy container MySQL 8.0.36 tạm thời).

---

## 2. HƯỚNG DẪN CÀI ĐẶT TRÊN MÁY MỚI (SETUP STEPS)

```bash
# 1. Clone mã nguồn về máy cục bộ:
git clone https://github.com/Bee-Max-Ne/Snipe-IT.git
cd Snipe-IT

# 2. Chuyển sang nhánh kiểm thử của Phân vùng 4:
git checkout feature/phan-vung-4-kiem-thu-an-ninh

# 3. Cài đặt các gói phụ thuộc PHP (Composer):
composer install --prefer-dist --no-interaction --no-scripts

# 4. Cài đặt các gói phụ thuộc JavaScript & Testcontainers:
npm install --no-audit --no-fund

# 5. Khởi tạo file cấu hình môi trường kiểm thử:
cp .env.testing.example .env
php artisan key:generate --env=testing
```

---

## 3. CÁC CÁCH KÍCH HOẠT KIỂM THỬ (TEST EXECUTION MODES)

### Cách 1: Chạy tự động qua K16 Testcontainers trên Windows (Khuyên dùng)
Đảm bảo Docker Desktop đang bật, sau đó chạy trực tiếp script đã chuẩn hóa:
```powershell
# Chạy toàn bộ 115 tests (Unit + Feature/API Integration) trên Docker MySQL 8.0.36
.\run_part4_tests.ps1

# Hoặc chạy nhanh 26 lượt kiểm thử an ninh trọng tâm (TC-SEC-* & TC-SEC-API-*)
.\run_part4_tests.ps1 -Filter test_tc_sec_
```
*(Nếu máy chưa bật Docker và cần chạy nhanh bằng PHPUnit cục bộ, thêm cờ `-FallbackLocal`.)*

### Cách 2: Chạy trực tiếp qua lệnh K16 Testcontainers (`tests/testcontainers/run.mjs`)
Lệnh này tự động kéo image `mysql:8.0.36`, khởi tạo container + database ngẫu nhiên + cặp khóa RSA Passport động, chạy `phpunit.part4.xml` và hủy container sạch sẽ ở khối `finally`:
```bash
# Trên Linux / macOS (ép pdo_mysql dùng TCP thay cho Unix socket)
TESTCONTAINERS_HOST_OVERRIDE=127.0.0.1 node tests/testcontainers/run.mjs -c phpunit.part4.xml

# Trên Windows PowerShell
$env:PHP_BINARY = (Get-Command php).Source
node tests/testcontainers/run.mjs -c phpunit.part4.xml
```

### Cách 3: Đóng gói và thu thập bằng chứng kiểm thử chuẩn công nghiệp (Artifact Packaging)
```powershell
.\export_evidence.ps1 -Filter test_tc_sec_
```
*Kết quả:* Toàn bộ bằng chứng trong `storage/logs/test-reports/` (`junit_part4_report.xml`, `phpunit_part4_execution.log`, `testdox.html`, `mysql_container_proof.json`, `environment_metadata.json`, `git_commit_evidence.txt`, `SUMMARY_REPORT.md`, `checksums.sha256`) được nén tự động vào `storage/logs/artifacts/Evidence_PhanVung4_v1.0.zip`.

---

## 4. MA TRẬN CA KIỂM THỬ AN NINH & TÍCH HỢP PHÂN VÙNG 4

| Mã Ca Test | Tên Kịch Bản Kiểm Thử | Tệp Nguồn Test | Mục Tiêu & Ranh Giới An Ninh | Kết Quả |
| :---: | :--- | :--- | :--- | :---: |
| **`TC-SEC-01`** | `test_tc_sec_01_superuser_has_unconditional_access_to_all_sections` | `tests/Unit/UserTest.php` | Superuser bỏ qua mọi cổng phân quyền lẻ (God Mode Bypass). | **PASS** |
| **`TC-SEC-02`** | `test_tc_sec_02_user_with_granular_permissions_is_strictly_bounded` | `tests/Unit/UserTest.php` | Người dùng thường bị giới hạn đặc quyền tối thiểu (Least Privilege). | **PASS** |
| **`TC-SEC-03`** | `test_tc_sec_03_explicit_user_deny_overrides_group_permission_allow` | `tests/Unit/UserTest.php` | Phủ quyết đích danh (`-1`) ghi đè cấp phép nhóm (`1`) (DEFECT-SEC-03). | **PASS** |
| **`TC-SEC-04`** | `test_tc_sec_04_permissions_accumulated_from_multiple_groups` | `tests/Unit/UserTest.php` | Tích lũy và kế thừa quyền hạn hợp nhất từ nhiều nhóm vai trò. | **PASS** |
| **`TC-SEC-05`** | `test_tc_sec_05_default_deny_when_no_permissions_assigned` | `tests/Unit/UserTest.php` | Phòng thủ chiều sâu: mặc định từ chối khi không có quyền (Deny by Default). | **PASS** |
| **`TC-SEC-06`** | `test_tc_sec_06_regular_user_cannot_create_or_delete_users_via_policy` | `tests/Unit/UserTest.php` | Chống leo thang đặc quyền tạo và xóa người dùng qua `UserPolicy`. | **PASS** |
| **`TC-SEC-07`** | `test_tc_sec_07_subadmin_cannot_modify_superuser_auth_fields` | `tests/Unit/UserTest.php` | Bảo vệ trường xác thực của SuperAdmin qua Gate `canEditAuthFields`. | **PASS** |
| **`TC-SEC-08`** | `test_tc_sec_08_impersonation_boundaries_enforced` | `tests/Unit/UserTest.php` | Ranh giới an toàn mạo danh: chặn tự mạo danh, mạo danh Superadmin (DEFECT-SEC-04). | **PASS** |
| **`TC-SEC-09`** | `test_tc_sec_09_admin_cannot_delete_themselves` | `tests/Unit/UserTest.php` | Chặn Admin tự xóa chính tài khoản đang đăng nhập (`DeleteUserRequest`). | **PASS** |
| **`TC-SEC-10`** | `test_tc_sec_10_companied_admin_cannot_grant_floater_status` | `tests/Unit/UserTest.php` | Chặn Admin chi nhánh gán quyền Floater toàn tập đoàn (`DEFECT-SEC-02`). | **PASS** |
| **`TC-SEC-11`** | `test_tc_sec_11_company_scoped_user_cannot_see_other_companies_users_in_strict_mode` | `tests/Unit/CompanyScopingTest.php` | Cô lập CSDL đa người thuê: User Cty A tuyệt đối không thấy User Cty B. | **PASS** |
| **`TC-SEC-12`** | `test_tc_sec_12_company_scoped_user_cannot_see_other_companies_users_in_floater_mode` | `tests/Unit/CompanyScopingTest.php` | Ngăn rò rỉ dữ liệu qua subquery khi bật Floater mode (Khóa ticket 56305). | **PASS** |
| **`TC-SEC-13`** | `test_tc_sec_13_cross_company_asset_checkout_strictly_prevented` | `tests/Unit/CompanyScopingTest.php` | Cấm bàn giao tài sản Công ty A cho nhân sự Công ty B (`canCheckoutTo`). | **PASS** |
| **`TC-SEC-14`** | `test_tc_sec_14_null_company_scoping_matrix_across_all_models` | `tests/Unit/CompanyScopingTest.php` | Kiểm thử bao quát 6 models: Accessories, Assets, Components, Consumables, Departments, Licenses. | **PASS** |
| **`TC-SEC-15`** | `test_tc_sec_15_action_log_immutability_and_audit_trail_preservation` | `tests/Unit/UserTest.php` | Tính bất biến của `action_logs`, không mất dấu kiểm toán kể cả khi đối tượng bị xóa. | **PASS** |
| **`TC-SEC-API-01`** | `test_tc_sec_api_01_unauthenticated_api_request_is_rejected_with_401` | `tests/Feature/Users/SecurityPart4ApiIntegrationTest.php` | Chặn request API thiếu Passport Bearer Token với HTTP `401 Unauthorized`. | **PASS** |
| **`TC-SEC-API-02`** | `test_tc_sec_api_02_regular_user_blocked_from_admin_routes_and_privilege_escalation` | `tests/Feature/Users/SecurityPart4ApiIntegrationTest.php` | Chặn `/hardware/create` (`403`) và chặn tự gán `superuser=1` qua `PUT /api/v1/users`. | **PASS** |
| **`TC-SEC-API-03`** | `test_tc_sec_api_03_admin_cannot_delete_superadmin_via_web_or_api` | `tests/Feature/Users/SecurityPart4ApiIntegrationTest.php` | Ngăn Admin thường xóa tài khoản Superadmin qua Web & API. | **PASS** |
| **`TC-SEC-API-04`** | `test_tc_sec_api_04_login_brute_force_lockout_enforced_after_max_attempts` | `tests/Feature/Users/SecurityPart4ApiIntegrationTest.php` | Khóa đăng nhập Brute-force sau 5 lần sai mật khẩu (`login_attempts` + `ThrottlesLogins`). | **PASS** |
| **`TC-SEC-API-05`** | `test_tc_sec_api_05_fmcs_api_bearer_token_isolates_users_and_assets_across_companies` | `tests/Feature/Users/SecurityPart4ApiIntegrationTest.php` | Cô lập nhân sự và tài sản đa công ty qua Passport Bearer Token API. | **PASS** |
| **`TC-SEC-API-06`** | `test_tc_sec_api_06_impersonation_feature_flow_and_k16_mysql_container_proof` | `tests/Feature/Users/SecurityPart4ApiIntegrationTest.php` | Luồng HTTP Impersonation + `Actionlog` + xuất `mysql_container_proof.json` (MySQL 8.0.36). | **PASS** |

---

## 5. LỊCH SỬ COMMIT NGUYÊN TỬ (ATOMIC COMMITS)

Mỗi ca kiểm thử đều được cam kết độc lập kèm thông điệp tiếng Việt chuẩn Conventional Commits:
```text
440effb test(audit): kiểm thử TC-SEC-15 tính bất biến và không mất dấu của nhật ký kiểm toán action_logs
773d561 test(fmcs): kiểm thử TC-SEC-14 toàn vẹn dữ liệu tài sản không gắn công ty trên ma trận 6 models
fe36071 test(fmcs): kiểm thử TC-SEC-13 chặn hành vi bàn giao tài sản xuyên công ty cross-company checkout
3cbfd8d test(fmcs): kiểm thử TC-SEC-12 ngăn chặn rò rỉ dữ liệu người dùng qua subquery khi bật floater mode
eaeaf17 test(fmcs): kiểm thử TC-SEC-11 cô lập dữ liệu người dùng đa công ty ở chế độ nghiêm ngặt strict mode
d898398 test(policy): kiểm thử TC-SEC-10 ngăn chặn quản trị viên chi nhánh gán quyền floater toàn tập đoàn
e4e9958 test(policy): kiểm thử TC-SEC-09 ngăn chặn quản trị viên tự xóa chính tài khoản đang đăng nhập
9dfe295 test(policy): kiểm thử TC-SEC-08 thiết lập ranh giới an toàn cho tính năng mạo danh mayImpersonate
50eab46 test(policy): kiểm thử TC-SEC-07 bảo vệ trường xác thực tài khoản superadmin qua canEditAuthFields
17d1f50 test(policy): kiểm thử TC-SEC-06 chống leo thang đặc quyền tạo và xóa người dùng qua UserPolicy
f47f768 test(rbac): kiểm thử TC-SEC-05 cơ chế mặc định từ chối khi không có quyền hạn
c51e0db test(rbac): kiểm thử TC-SEC-04 tích lũy kế thừa quyền hạn từ nhiều nhóm vai trò
528b6b7 test(rbac): kiểm thử TC-SEC-03 giải quyết xung đột phủ quyết đích danh ghi đè cấp phép nhóm
78a05a1 test(rbac): kiểm thử TC-SEC-02 giới hạn quyền phân tầng đơn lẻ của người dùng
e29ae4d test(rbac): kiểm thử TC-SEC-01 quyền tối thượng của superuser (god mode bypass)
8b10ce6 docs(kế-hoạch): bổ sung kế hoạch kiểm thử chi tiết phân vùng 4 (Nguyễn Thành Minh - 2312691)
```
