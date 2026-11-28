# Báo cáo Tổng hợp Kết quả Triển khai 4 Phân vùng — Nhóm 05 (Snipe-IT R03 × K16 Testcontainers)

- **Kho mã nguồn:** `https://github.com/Bee-Max-Ne/Snipe-IT` (nhánh `main`, commit chốt: `e66cafa`)
- **Workflow CI/CD tổng hợp 4 phân vùng:** [`.github/workflows/testcontainers_all_4_parts.yml`](../.github/workflows/testcontainers_all_4_parts.yml) — Run `#46152085092` (**5/5 jobs `success`**)
- **Kỹ thuật kiểm thử cốt lõi:** K16 Testcontainers (`mysql:8.0.36` tạm thời) + đo độ phủ **PCOV 1.0.12** + khóa toàn vẹn **SHA-256**.

---

## 1. Kết quả Triển khai 4 Phân vùng & So sánh Local vs CI/CD

| Phân vùng & Phụ trách | Cấu hình cô lập | Local Docker Windows (+ PCOV) | GitHub Actions Linux (Run `#46152085092`) | Artifact CI |
| :--- | :--- | :--- | :--- | :--- |
| **PV1 (`2312718`)** — Checkout, Checkin & Race Guard | `phpunit.part1.xml` | 419 tests, 1,434 assertions, 0 lỗi (1 incomplete), `51m 40s`, PCOV **`22.45%`** (`411/1,831`) | 419 tests, `3m 08s`, PCOV **`22.45%`** | `evidence-part1` (`0.85 MB`) |
| **PV2 (`2312729`)** — Hardware, Model & Khấu hao | `phpunit.part2.xml` | 110 core: `12m 52s` (`20.87%`); 638 tests chạm `set_time_limit(600s)` ở ~59% | 638 tests, 2,038 assertions, 0 lỗi (35 skipped gốc), `5m 42s`, PCOV **`60.73%`** (`1,452/2,391`) | `evidence-part2` (`0.98 MB`) |
| **PV3 (`2314506`)** — License, Phụ kiện & Hạ tầng K16 | `phpunit.part3.xml` | 443 tests (+2 `TC-INF-01`), 1,587 assertions, 0 lỗi, `42m 04s`, PCOV **`68.27%`** (`1,136/1,664`) | 443 tests (+2 `TC-INF-01`), `4m 11s`, PCOV **`68.27%`** | `evidence-part3` (`0.57 MB`) |
| **PV4 (`2312691`)** — RBAC, Policy, API & FMCS | `phpunit.part4.xml` | 561 tests, 2,138 assertions, 0 lỗi, `43m 32s`, PCOV **`82.60%`** (`1,828/2,213`) | 561 tests, `3m 22s`, PCOV **`82.78%`** (`1,832/2,213`) | `evidence-part4` (`1.72 MB`) |

### Giải thích tỷ lệ PCOV của từng phân vùng
- **PV1 (`22.45%`):** Mẫu số `<source>` gồm trọn `Api\AssetsController.php` (`1,052` dòng, 35 methods) và `Asset.php` (`619` dòng) dùng chung cho cả CRUD/bulk/import/audit/labels, trong khi PV1 chỉ kiểm thử chuyên sâu luồng `checkout`/`checkin`.
- **PV2 (`60.73%`):** Tăng gấp 3 lần khi chạy trọn `Feature/Assets`, `AssetModels`, `Depreciations`, `CustomFields`; phần chưa phủ chủ yếu do `35` test gốc Snipe-IT tự đánh dấu skip/incomplete và các nhánh `checkout`/`checkin` thuộc phạm vi PV1.
- **PV3 (`68.27%`):** Các tệp lõi đạt cao (`AdjustsQuantity` `89.19%`, `License` `79.12%`, `AccessoriesController` `76.64%`, `LicensesController` `75.90%`), phần ~31% còn lại nằm ở các hàm quản trị/bulk phụ của `ComponentsController` & `ConsumablesController`.
- **PV4 (`82.78%` CI / `82.60%` local):** Vượt ngưỡng `>= 80%` nhờ 10 tệp `<source>` khớp sát 1-1 với bộ `Feature/Users`, `Groups`, `Auth`, `Authentication`; phần ~17% chưa phủ là nhánh tích hợp LDAP/SAML ngoài trong `LoginController`.

### Cách đo độ bao phủ & Tính chuẩn mực quốc tế
- **Cách đo:** Bật driver **PCOV 1.0.12** khi chạy PHPUnit 11.5 (`--coverage-clover`, `--coverage-html`) trên tập tệp nguồn `<source>` của `phpunit.part1..4.xml`. Công thức tính:
  - **Line / Statement Coverage (%):** `(Số dòng lệnh đã thực thi / Tổng số dòng lệnh thực thi trong <source>) × 100`
  - **Method Coverage (%):** `(Số phương thức được phủ hoàn toàn / Tổng số phương thức trong <source>) × 100`
- **Chuẩn quốc tế:** **Có.** Công thức *Statement/Line Coverage* là chỉ số tiêu chuẩn của **ISTQB** và **ISO/IEC/IEEE 29119** cho kiểm thử hộp trắng; đồng thời định dạng **`coverage_clover.xml`** tuân thủ chuẩn **Clover XML** (được SonarQube, Codecov, GitHub Actions, GitLab CI sử dụng rộng rãi).

---

## 2. Tác dụng của từng tệp Log & Minh chứng

| Tệp Log / Minh chứng | Nguồn sinh ra | Tác dụng & Chứng minh điều gì? |
| :--- | :--- | :--- |
| `phpunit_partX_execution.log` | Console PHPUnit | Ghi toàn bộ quá trình chạy thật, số Pass/Fail và thời gian thực thi. |
| `junit_partX_report.xml` | PHPUnit (`--log-junit`) | Dữ liệu chuẩn XML để CI/CD đếm chính xác `Tests`, `Assertions`, `Failures`, `Errors`, `Skipped`. |
| `testdox.html` | PHPUnit (`--testdox-html`) | Danh sách từng ca kiểm thử dạng văn bản dễ đọc kèm dấu tích xanh `✓ PASS`. |
| `coverage_clover.xml` & `coverage-html/` | Driver **PCOV 1.0.12** | Chứng minh tỷ lệ `%` dòng lệnh/hàm được test chạm tới (bôi xanh/đỏ trực quan từng dòng code). |
| `mysql_container_proof.json` | Test `TC-SEC-API-06` (PV4) | Bằng chứng test kết nối thật vào container `mysql:8.0.36` (không chạy giả bằng SQLite). |
| `environment_metadata.json` & `git_commit_evidence.txt` | Script & Git | Ghi lại phiên bản PHP/OS/Node và lịch sử commit tại thời điểm chạy để tái lập. |
| `checksums.sha256` | `sha256sum` / `Get-FileHash` | Mã băm khóa toàn vẹn: chứng minh các file log/XML không bị chỉnh sửa bằng tay. |

---

## 3. Hướng dẫn Chạy lại Kiểm thử trên Máy mới

### Tiền điều kiện
- PHP `>= 8.2` (bật `curl`, `exif`, `fileinfo`, `gd`, `mbstring`, `pdo_mysql`, `sodium`, `zip`, `pcov`), Composer `2.x`, Node.js `>= 18`, và **Docker Desktop** đang chạy.

### Các bước thực thi
```powershell
# 1. Cài đặt phụ thuộc & khởi tạo .env
composer install --prefer-dist --no-interaction --no-scripts
npm install --no-audit --no-fund
cp .env.testing.example .env
php artisan key:generate --env=testing

# 2. Chạy cả 4 phân vùng trên Windows PowerShell (+ PCOV + SHA-256 + SUMMARY_ALL_4_PARTS.md):
.\export_all_4_parts_evidence.ps1

# 3. Hoặc chạy từng phân vùng riêng qua K16 Testcontainers:
$env:PHP_BINARY = (Get-Command php).Source
node tests/testcontainers/run.mjs -c phpunit.part1.xml
node tests/testcontainers/run.mjs -c phpunit.part2.xml
node tests/testcontainers/run.mjs -c phpunit.part3.xml
node tests/testcontainers/run.mjs -c phpunit.part4.xml

# 4. Chạy test hạ tầng dọn container khi crash (PV3 TC-INF-01):
npm run test:k16:infra
```
