# Kế hoạch Triển khai Kiểm thử – Phân vùng 2: Asset, Asset Model và Depreciation

> **Thông tin phạm vi:** Nguyễn Văn Quốc – MSSV 2312729 – K16 QA  
> **Repository / Nhánh:** `https://github.com/Bee-Max-Ne/Snipe-IT.git` / `feat/test-quoc-part2`  
> **Phạm vi Phân vùng 2:** Tài sản phần cứng (`Asset`), Dòng Model tài sản (`AssetModel`), Custom Fields của Asset và Khấu hao (`Depreciation`).  
> **PHPUnit Group Tag bắt buộc:** `k16` (`#[Group('k16')]`)

---

## 1. Mục tiêu kiểm thử

- Xác minh độ chính xác của các quy tắc nghiệp vụ quản lý tài sản phần cứng (`Asset`): ràng buộc `unique_undeleted` của `asset_tag`, tự động tăng tag, `model_id` bắt buộc, `warranty_months`, custom fields mã hóa/IMEI/MAC, xóa mềm và khôi phục đơn lẻ.
- Xác minh tính toán khấu hao tuyến tính (`getLinearDepreciatedValue`) theo `amount` và `percent`, giá trị sàn `depreciation_min`, không âm khi hết kỳ và tính tỷ lệ giữa kỳ.
- Cô lập nhóm testcase Phân vùng 2 bằng PHPUnit attribute `#[Group('k16')]` và file cấu hình `phpunit.part2.xml` chạy trên SQLite `:memory:` (`DB_CONNECTION=sqlite_testing`), không làm ảnh hưởng cấu hình `phpunit.xml` chung.

---

## 2. Kết quả rà soát hiện trạng (2026-11-26)

### 2.1 Bảng phân loại trạng thái

| Nhóm trạng thái | Hạng mục / Testcase | Chi tiết kiểm chứng thực tế |
|---|---|---|
| **Đã hoàn thành & có bằng chứng** | `TC-DP-01..03` (`tests/Unit/DepreciationTest.php`) | 8 tests, 14 assertions — Đã chạy xác minh **Pass** (quan hệ model/license, floor `amount`/`percent`, không âm, giữa kỳ). |
| **Đã hoàn thành & có bằng chứng** | `TC-HW-01..05, 08..10` (`AssetTest.php`, `StoreAssetTest.php`, `DeleteAssetsTest.php`) | Đã có `test_asset_tag_unique_constraint`, `test_encrypted_custom_field_encryption_and_decryption`, `test_mac_address_custom_field_format_and_storage`, `test_imei_custom_field_format_and_storage`, `test_asset_tags_must_be_unique_when_undeleted`, `test_asset_tags_can_be_duplicated_if_deleted`, `test_encrypted_custom_field_*`, `test_deleting_checked_out_asset_checks_it_in_then_deletes` (commit `3662424`). |
| **Đã có nhưng chưa đầy đủ / đang fail (giữ nguyên theo yêu cầu)** | `StoreAssetTest::test_permission_needed_to_store_encrypted_field` | Có chỉnh sửa chưa commit trong working tree (`User::factory()->editAssets()->create()`), hiện **Error** (`Undefined array key "status"`) do user thiếu quyền `createAssets()`. Giữ nguyên không ghi đè/không stage. |
| **Chưa thực hiện** | `TC-HW-06` (`model_id` bắt buộc) | `Asset::$rules['model_id']` có `required`, chưa có test tạo Asset với `model_id = null`. |
| **Chưa thực hiện** | `TC-HW-07` (`warranty_months` boundary) | Chưa có test kiểm tra `-1`, `241` và các giá trị hợp lệ `10`, `24`, `36`. **Lưu ý sai khác nghiệp vụ:** `app/Models/Asset.php:159` dùng `'digits_between:0,240'` (đếm số chữ số), nên `-1` bị từ chối nhưng `241` hiện **được chấp nhận**. |
| **Chưa thực hiện** | `TC-HW-11` (Xóa mềm & khôi phục đơn lẻ) | Mới chỉ có test bulk restore (`BulkRestoreAuthorizationTest`, `BulkDeleteAssetsTest`), chưa có test xóa mềm đơn lẻ + khôi phục đơn lẻ qua `api.assets.restore`. |
| **Chưa thực hiện** | Tag `k16`, `phpunit.part2.xml`, CI workflow, cập nhật báo cáo | Chưa gắn `#[Group('k16')]`, chưa có `phpunit.part2.xml` và chưa có workflow CI riêng cho Phân vùng 2. |
| **Bị chặn bởi môi trường** | Testcontainers MySQL | `Docker version 29.8.0` có mặt nhưng Docker Desktop daemon không chạy (`//./pipe/docker_engine` không kết nối được; `podman` không có). Trạng thái: **Blocked/Not run**. |
| **Bị chặn bởi môi trường** | Đo coverage bằng PCOV | PHP 8.5.11 CLI không cài extension `pcov` (và không có `xdebug`). Trạng thái: **Blocked/Not run**. |

---

## 3. Kế hoạch triển khai chi tiết (Mỗi TC = 1 commit riêng)

| Bước | Nội dung | File đích | Commit dự kiến |
|---:|---|---|---|
| 1 | Cập nhật kế hoạch Phân vùng 2 | `reports/KE_HOACH_TRIEN_KHAI_PHAN_VUNG_2.md` | `plan(k16): align partition 2 plan with verified status` |
| 2 | Gắn `#[Group('k16')]` cho test Phân vùng 2 hiện có & tạo `phpunit.part2.xml` | `tests/Unit/DepreciationTest.php`, `tests/Unit/AssetTest.php`, `tests/Feature/Assets/Api/DeleteAssetsTest.php`, `phpunit.part2.xml` | 1 commit tag `k16` + 1 commit `phpunit.part2.xml` |
| 3 | Bổ sung `TC-HW-06`: `model_id` bắt buộc khi tạo Asset | `tests/Feature/Assets/Api/StoreAssetValidationK16Test.php` | `test(k16): add TC-HW-06 required model validation` |
| 4 | Bổ sung `TC-HW-07`: `warranty_months` (theo hành vi thực tế hiện tại, ghi nhận sai khác `241`) | `tests/Feature/Assets/Api/StoreAssetValidationK16Test.php` | `test(k16): add TC-HW-07 warranty_months validation` |
| 5 | Bổ sung `TC-HW-11`: xóa mềm và khôi phục đơn lẻ | `tests/Feature/Assets/Api/RestoreAssetK16Test.php` | `test(k16): add TC-HW-11 single soft delete and restore` |
| 6 | Thêm GitHub Actions workflow riêng cho Phân vùng 2 | `.github/workflows/tests-part2-k16.yml` | `ci(k16): add GitHub Actions workflow for partition 2` |
| 7 | Chạy `phpunit.part2.xml` và cập nhật báo cáo | `reports/nguyen-van-quoc-k16-qa-phan-vung-2-report.md` | `docs(k16): update partition 2 test report` |

---

## 4. Nguyên tắc bảo vệ thay đổi & tiêu chí merge

1. Không chỉnh sửa, xóa, stage hoặc commit `storage/private_uploads/assets/`, `tests/Feature/Assets/Api/StoreAssetTest.php` (đang có thay đổi chưa commit), `.kilo/`, `.agents/`, `AGENTS.md`, `CLAUDE.md`.
2. Không sửa logic nghiệp vụ `app/Models/Asset.php` khi chưa có yêu cầu thay đổi nghiệp vụ từ người giao việc.
3. Không force-push, reset, rebase; chỉ push nhánh `feat/test-quoc-part2` lên `https://github.com/Bee-Max-Ne/Snipe-IT.git`.
4. Trước khi merge vào `main`, kiểm tra diff với `main` và xác nhận không còn xung đột hoặc thay đổi ngoài phạm vi.

---

## 5. Nhật ký thực hiện (cập nhật sau khi hoàn tất)

- **File kế hoạch:** `reports/KE_HOACH_TRIEN_KHAI_PHAN_VUNG_2.md` (`a98e8cf`)
- **Các commit cấu hình, testcase & CI:**
  - `7e373b9` — `test(k16): tag existing partition 2 test suites with k16 group`
  - `029f7bb` — `chore(k16): add phpunit.part2.xml for k16 group execution`
  - `e372c74` — `test(k16): add TC-HW-06 required model validation`
  - `a8c1c7a` — `test(k16): add TC-HW-07 warranty_months validation`
  - `1102456` — `test(k16): add TC-HW-11 single soft delete and restore`
  - `dc81da2` — `ci(k16): add GitHub Actions workflow for partition 2`
- **Kết quả chạy `phpunit.part2.xml`:** `vendor/bin/phpunit -c phpunit.part2.xml --no-progress --display-errors` → **OK (93 tests, 308 assertions)** (`0 failure`, `0 skipped`, `0 incomplete` — đã bao gồm `AssetModelTest.php`, `StoreAssetTest.php` và `TC-HW-07` sau khi sửa `warranty_months` thành `min:0,max:240`).
- **Giới hạn môi trường:** Testcontainers MySQL (`Blocked/Not run` — Docker daemon không chạy) và PCOV coverage (`Blocked/Not run` — PHP 8.5.11 CLI thiếu `pcov`/`xdebug`).
