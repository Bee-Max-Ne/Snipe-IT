# KẾ HOẠCH KIỂM THỬ PHẦN MỀM CHI TIẾT — PHÂN VÙNG 2

> **ĐỀ TÀI:** KIỂM THỬ HỆ THỐNG QUẢN TRỊ TÀI SẢN CNTT SNIPE-IT VỚI KỸ THUẬT TESTCONTAINERS (K16)  
> **HỌC PHẦN:** Kiểm thử Phần mềm | **MÃ ĐỀ TÀI:** R03  
> **CƠ SỞ ĐÀO TẠO:** Trường Đại học Đà Lạt — Khoa Công nghệ Thông tin — Bộ môn Công nghệ Phần mềm  
> **GIẢNG VIÊN HƯỚNG DẪN:** ThS. Nguyễn Thế Lâm

---

## THÔNG TIN SINH VIÊN & PHÂN VÙNG TRÁCH NHIỆM

- **Họ và tên:** Nguyễn Văn Quốc
- **Mã số sinh viên:** `2312729`
- **Vai trò:** Thành viên 2 — QA Engineer; Kỹ sư kiểm thử Logic Phần cứng & Thuật toán Khấu hao
- **Phân vùng:** Phân vùng 2 — Quản lý Tài sản Phần cứng, Dòng Model & Khấu hao
- **Git feature branch dự kiến:** `feat/k16-quoc-clean-pr` (chỉ sử dụng sau khi xác minh nhánh và working tree an toàn)
- **Repository được nêu trong yêu cầu triển khai:** `D:\KiemThu\Snipe-IT` (phải xác minh lại đường dẫn repository thực tế trước khi thao tác; thực tế đang làm việc tại `D:\Snipe-IT`)
- **Các bộ test được phân công trong báo cáo:**
  - `tests/Unit/AssetTest.php`
  - `tests/Unit/DepreciationTest.php`
  - `tests/Unit/AssetModelTest.php`
- **Nguồn yêu cầu chính:** `BAO_CAO_GK_Nhom05.docx`, mục 2.3.3.2, ma trận phân vùng tại mục 2.3.3 và nhiệm vụ cá nhân tại mục 4.2.2.
- **Tài liệu tham khảo về cấu trúc kế hoạch:** `KE_HOACH_TRIEN_KHAI_PHAN_VUNG_4.md`. Tài liệu Phân vùng 4 chỉ được dùng để tham khảo cách tổ chức kế hoạch, không dùng để thay thế yêu cầu nghiệp vụ của Phân vùng 2.
- **Quy ước trạng thái:** `PLANNED` = dự kiến; `IN PROGRESS` = đang thực hiện; `PASS` / `FAIL` = đã có kết quả chạy thực tế; `BLOCKED` = có bằng chứng về blocker; `NOT RUN` = chưa chạy; `NEEDS VERIFICATION` = thông tin cần đối chiếu trực tiếp với source code/schema. Không ghi `PASS` hoặc `COVERED` chỉ dựa trên tên method hay nội dung kế hoạch cũ.

---

## MỤC LỤC

1. [Tổng quan nhiệm vụ và ranh giới phân vùng](#1-tổng-quan-nhiệm-vụ-và-ranh-giới-phân-vùng)
2. [Cơ sở kỹ thuật K16, giới hạn và tiêu chí áp dụng](#2-cơ-sở-kỹ-thuật-k16-giới-hạn-và-tiêu-chí-áp-dụng)
3. [Phân tích rủi ro và thiết kế kịch bản kiểm thử](#3-phân-tích-rủi-ro-và-thiết-kế-kịch-bản-kiểm-thử)
   - [3.1. Test Model và rủi ro nghiệp vụ](#31-mô-hình-rủi-ro-nghiệp-vụ-risk-model)
   - [3.2. Ma trận Test Suite](#32-ma-trận-test-suite-tổng-quát)
   - [3.3. Đặc tả chi tiết TC-HW-01 đến TC-HW-11 và TC-DP-01 đến TC-DP-03](#33-đặc-tả-chi-tiết-test-case-chính)
   - [3.4. Phạm vi kiểm thử bổ sung cho AssetModel](#34-phạm-vi-kiểm-thử-bổ-sung-cho-assetmodel)
4. [Kế hoạch triển khai mã kiểm thử tự động](#4-kế-hoạch-triển-khai-mã-kiểm-thử-tự-động)
5. [Quy trình thực thi SQLite và Docker/K16, thu thập bằng chứng và đo lường](#5-quy-trình-thực-thi-thu-thập-bằng-chứng-và-đo-lường)
6. [Quản lý lỗi và phân tích nguyên nhân gốc rễ](#6-quản-lý-lỗi-và-phân-tích-nguyên-nhân-gốc-rễ)
7. [Lộ trình triển khai và tiêu chí nghiệm thu](#7-lộ-trình-triển-khai-và-tiêu-chí-nghiệm-thu)
8. [Quy trình Git và chiến lược commit từng Test Case](#8-quy-trình-git-và-chiến-lược-commit-từng-test-case)
9. [Bảng theo dõi tiến độ và kết quả](#9-bảng-theo-dõi-tiến-độ-và-kết-quả)
10. [Danh mục bằng chứng nghiệm thu](#10-danh-mục-bằng-chứng-nghiệm-thu)

---

## 1. TỔNG QUAN NHIỆM VỤ VÀ RANH GIỚI PHÂN VÙNG

### 1.1. Mục tiêu kiểm thử

Phân vùng 2 tập trung xác minh tính đúng đắn của lớp miền và các quy tắc nghiệp vụ liên quan đến tài sản phần cứng, dòng Model và khấu hao trong Snipe-IT. Các mục tiêu chính:

- Đảm bảo mã định danh tài sản `asset_tag` không bị trùng trong các luồng tạo tài sản và tự động sinh mã `autoincrement_asset`.
- Kiểm chứng thuật toán khấu hao đường thẳng `getLinearDepreciatedValue`, bao gồm phép tính giá trị còn lại, giới hạn sàn `depreciation_min` và việc không tạo giá trị sổ sách âm.
- Kiểm tra các quan hệ và hành vi nghiệp vụ của `AssetModel` theo implementation thực tế.
- Kiểm chứng việc lưu trữ, định dạng và mã hóa các Custom Fields, trọng tâm là IMEI và MAC Address.
- Xác minh validation và tính toàn vẹn dữ liệu liên quan đến `model_id`, `warranty_months`, `purchase_cost`, soft delete và restore.
- Ghi nhận rõ những khác biệt giữa hành vi mô tả trong báo cáo, kế hoạch cũ và implementation thực tế; không sửa nghiệp vụ/schema chỉ để ép test pass.

### 1.2. Phạm vi trách nhiệm theo báo cáo nhóm

| Hạng mục | Phạm vi Phân vùng 2 |
|---|---|
| **Hardware Asset** | `asset_tag`, tự tăng mã, tạo tài sản, validation, soft delete/restore |
| **Asset Model** | Model `AssetModel`, quan hệ Eloquent và truy vấn tài sản theo Model |
| **Depreciation** | `getLinearDepreciatedValue`, `depreciation_min`, tỷ lệ khấu hao |
| **Custom Fields** | Lưu trữ, định dạng, mã hóa/giải mã IMEI và MAC Address |
| **Kiểm thử** | Unit / Business Logic Testing; PHPUnit trên SQLite và MySQL theo cấu hình dự án |

### 1.3. Ngoài phạm vi

Không triển khai lại hoặc nhận trách nhiệm chính cho các module của thành viên khác:

- Checkout/Checkin, quy trình cấp phát/thu hồi, OAuth2 API end-to-end và locking của luồng checkout (Phân vùng 1).
- License Management, Accessory inventory adjustment, Component inventory adjustment và Consumables (Phân vùng 3).
- RBAC, `UserPolicy`, `CompanyableScope`/FMCS và bảo mật phân quyền tổng thể (Phân vùng 4).
- Hạ tầng runner Testcontainers `tests/testcontainers/run.mjs`, Passport key generation và GitHub Actions nếu các phần này đã được giao cho Infrastructure Lead.
- Có thể sử dụng dữ liệu Component/Custom Field/Model làm dependency để kiểm tra kết quả của Phân vùng 2, nhưng không thay đổi nghiệp vụ tồn kho hoặc module do thành viên khác phụ trách.

### 1.4. File source code cần khảo sát

| Nhóm | File / vị trí cần kiểm tra | Mục đích |
|---|---|---|
| Domain Model | `app/Models/Asset.php` | Tag, autoincrement, validation, component value, soft delete |
| Domain Model | `app/Models/AssetModel.php` | Quan hệ, truy vấn theo Model, phương thức tính toán hiện có |
| Domain Model | `app/Models/Depreciation.php` | Cấu hình khấu hao và loại giá trị sàn |
| Domain Model / Trait | `app/Models/Depreciable.php` (nếu tồn tại) | Implementation `getLinearDepreciatedValue` và phép tính liên quan |
| Custom Fields | `app/Models/CustomField.php` | Mã hóa, giải mã, kiểm tra kiểu/định dạng và quyền xem |
| Request | `app/Http/Requests/StoreAssetRequest.php` | Chuẩn hóa request, tự sinh tag và giá mua |
| Helper | `app/Helpers/Helper.php` (nếu có `ParseCurrency()`) | Chuẩn hóa tiền tệ |
| Schema | Migrations tạo/sửa bảng `assets`, `models`, `depreciations`, `custom_fields` | Ràng buộc, index, cột động và kiểu dữ liệu |
| Tests | `tests/Unit/AssetTest.php` | Test tài sản |
| Tests | `tests/Unit/DepreciationTest.php` | Test khấu hao |
| Tests | `tests/Unit/AssetModelTest.php` | Test Model |
| Feature tests | `tests/Feature/Assets/`, `tests/Feature/AssetModels/` | Chỉ bổ sung khi cần kiểm chứng luồng request/persistence/UI/API |

Danh sách trên là danh sách cần khảo sát, không khẳng định mọi file hoặc method đều tồn tại trong phiên bản repository hiện tại. Phải tìm kiếm và xác minh trước khi tạo test hoặc ghi tên method cụ thể vào báo cáo.

---

## 2. CƠ SỞ KỸ THUẬT K16, GIỚI HẠN VÀ TIÊU CHÍ ÁP DỤNG

### 2.1. Test Model

Sử dụng **Financial Calculation & Database Integrity Model**: kết hợp kiểm thử toán học ở mức Unit Test với kiểm thử persistence/ràng buộc dữ liệu trên CSDL thực. Tách phần kiểm tra logic thuần khỏi phần phụ thuộc database để xác định chính xác nơi phát sinh lỗi.

- **Financial Calculation:** kiểm tra công thức khấu hao, số tháng đã sử dụng, tỷ lệ phần trăm và mức sàn.
- **Database Integrity:** kiểm tra tạo tài sản, tag trùng, model tham chiếu, soft delete/restore và dữ liệu Custom Fields.
- **Boundary Value Analysis (BVA):** kiểm tra các giá trị biên của tháng bảo hành, tháng khấu hao và giá trị sàn.
- **Equivalence Partitioning:** phân vùng dữ liệu hợp lệ/không hợp lệ cho Asset Tag, Model và chuỗi tiền tệ.
- **Concurrency Testing:** chỉ áp dụng cho tình huống hai phiên cùng lấy/sử dụng mã tự tăng; cần harness và DB phù hợp để mô phỏng có kiểm soát.

### 2.2. Vì sao cần đối chiếu SQLite và MySQL

SQLite có thể dùng để phản hồi nhanh khi phát triển Unit Test. Tuy nhiên, kết quả SQLite không thay thế được kết quả MySQL đối với các hành vi phụ thuộc engine, kiểu dữ liệu, index, transaction, migration hoặc cột Custom Field động.

| Khía cạnh | SQLite testing | MySQL 8.0 qua Testcontainers |
|---|---|---|
| **Phản hồi phát triển** | Phù hợp cho vòng lặp nhanh nếu test tương thích | Chậm hơn do khởi tạo container và migration |
| **Kiểu dữ liệu / collation** | Có khác biệt với MySQL | Là môi trường xác minh DB mục tiêu theo báo cáo |
| **Ràng buộc và index** | Có thể khác schema/engine thực tế | Cần xác minh trên schema MySQL được migration tạo ra |
| **Transactions / concurrency** | Không đại diện đầy đủ cho InnoDB | Dùng để xác minh các hành vi phụ thuộc MySQL |
| **Custom Fields động** | Có thể bị giới hạn bởi cơ chế schema riêng của ứng dụng | Cần migration và cấu hình dynamic columns đúng |

### 2.3. Tiêu chí áp dụng K16 cho Phân vùng 2

Ưu tiên chạy MySQL Testcontainers cho các ca có yếu tố database integrity:

- **TC-HW-01, TC-HW-02, TC-HW-03, TC-HW-04, TC-HW-05:** tạo tài sản, trùng tag, autoincrement và xử lý tranh chấp.
- **TC-HW-06, TC-HW-07, TC-HW-08:** validation và chuẩn hóa dữ liệu được lưu.
- **TC-HW-09:** lưu Custom Fields mã hóa trên schema thật.
- **TC-HW-10:** xác minh phép tổng hợp giá trị dựa trên quan hệ dữ liệu nếu cần DB.
- **TC-HW-11:** soft delete/restore và truy vấn dữ liệu.
- **TC-DP-01 đến TC-DP-03:** ưu tiên Unit Test; chạy MySQL bổ sung nếu phép tính/quan hệ lấy dữ liệu từ database.
- **AssetModel tests:** dùng DB khi kiểm tra relation, scopes hoặc truy vấn.

Không sửa runner Testcontainers hoặc workflow CI/CD nếu không thuộc quyền phụ trách. Trước khi chạy, kiểm tra runner hiện có và truyền đúng tên file/test theo cú pháp hỗ trợ của dự án.

### 2.4. Giới hạn kỹ thuật và điểm cần xác minh trước

- **UNIQUE `asset_tag`:** Báo cáo giữa kỳ mô tả cột `asset_tag` có UNIQUE index và validation `unique_undeleted`. Tuy nhiên, tài liệu kế hoạch Phân vùng 2 trước đó lại ghi nhận khả năng DB chỉ có index thường và uniqueness được kiểm tra ở tầng model. Đây là mâu thuẫn cần giải quyết bằng migration, schema thực tế và test MySQL. Không được kết luận trước khi kiểm tra.
- **`warranty_months`:** Báo cáo yêu cầu biên 0–240. Kế hoạch trước đó nhận định rule `digits_between:0,240` có thể kiểm tra số chữ số thay vì giá trị số học. Cần kiểm tra rule Laravel thực sự đang dùng và chạy bốn giá trị `-1`, `0`, `240`, `241`.
- **Khấu hao:** Báo cáo nêu công thức đường thẳng. Cần đọc `getLinearDepreciatedValue`, `calculateDepreciation` và cấu hình floor thực tế; phân biệt floor kiểu `amount` và `percent` nếu ứng dụng hỗ trợ cả hai.
- **IMEI/MAC:** Không mặc định IMEI có predefined format riêng. Phải kiểm tra Custom Field type, regex/format và gate/permission hiện hành.
- **Component value:** TC-HW-10 chỉ xác minh tổng giá trị được hiển thị/tính bởi Asset nếu implementation hỗ trợ; không thực hiện điều chỉnh tồn kho Component thuộc Phân vùng 3.
- **QR Code:** Chỉ xác minh việc tạo/liên kết QR nếu code path hiện có hỗ trợ kiểm tra tự động; không đưa ra khẳng định chỉ dựa trên việc record đã lưu.

---

## 3. PHÂN TÍCH RỦI RO VÀ THIẾT KẾ KỊCH BẢN KIỂM THỬ

### 3.1. Mô hình rủi ro nghiệp vụ (Risk Model)

| Rủi ro | Hậu quả | Kiểm soát / Test Case ưu tiên |
|---|---|---|
| Trùng `asset_tag` | Không định danh duy nhất, sai lịch sử tài sản và kiểm kê | TC-HW-02; xác minh validation và DB constraint |
| Xung đột autoincrement | Hai tài sản nhận cùng mã hoặc mất lượt cấp mã | TC-HW-03, TC-HW-04, TC-HW-05 |
| Sai phép tính khấu hao | Báo cáo giá trị tài sản sai, sai quyết định tài chính | TC-DP-01, TC-DP-03 |
| Khấu hao vượt giá trị sàn | Giá trị sổ sách thấp hơn floor hoặc âm | TC-DP-02 |
| Tạo Asset không có Model hợp lệ | Dữ liệu mồ côi hoặc lỗi quan hệ | TC-HW-06 |
| Sai giới hạn bảo hành | Ngày hết hạn bảo hành sai hoặc dữ liệu không hợp lệ | TC-HW-07 |
| Sai chuẩn hóa tiền | Lưu sai nguyên giá, kéo theo khấu hao sai | TC-HW-08 |
| Custom Field nhạy cảm lưu plaintext / lộ qua giao diện | Lộ IMEI/MAC | TC-HW-09 |
| Tổng giá trị Component sai | Sai tổng giá trị tài sản | TC-HW-10 |
| Xóa/khôi phục sai vòng đời | Mất tài sản hoặc hiển thị sai danh sách | TC-HW-11 |
| Quan hệ `AssetModel` sai | Truy vấn model/depreciation/asset không chính xác | AssetModel supplemental tests |

### 3.2. Ma trận Test Suite tổng quát

| Nhóm | Mã nhóm | Test Case | Đối tượng kiểm tra | Phương pháp |
|---|---|---|---|---|
| Unique & Autoincrement | `GRP-HW-ID` | TC-HW-01..05 | Asset Tag, autoincrement, DB integrity | Unit + Feature/Integration + MySQL khi phù hợp |
| Linear Depreciation | `GRP-DEP` | TC-DP-01..03 | `getLinearDepreciatedValue`, floor, percentage | Unit / Business Logic |
| Asset Attributes | `GRP-HW-ATTR` | TC-HW-06..08 | Model validation, warranty, purchase cost | Unit + Feature |
| Custom Fields Security | `GRP-CF` | TC-HW-09 | IMEI, MAC, encryption/decryption, permission gate | Unit + Feature/Integration |
| Component Value | `GRP-COMP-VALUE` | TC-HW-10 | Asset component cost aggregation | Unit / Model |
| Asset Lifecycle | `GRP-HW-LIFE` | TC-HW-11 | Soft delete, restore, query visibility | Unit + Feature |
| Asset Model | `GRP-MODEL` | AM-01..05 (bổ sung sau khảo sát) | Quan hệ và hành vi thực tế của `AssetModel` | Unit / Model |

Tổng số ca chính theo báo cáo: **14** (11 TC-HW và 3 TC-DP). AssetModel supplemental tests: tối đa 5 mục kiểm tra bổ sung để đáp ứng bộ `AssetModelTest.php` được phân công; nội dung cuối cùng phải căn cứ method/relationship có thật trong source code, không tạo nghiệp vụ mới.

### 3.3. Đặc tả chi tiết Test Case chính

#### NHÓM A — TS-02.1: RÀNG BUỘC DUY NHẤT & CƠ CHẾ TỰ TĂNG MÃ TÀI SẢN

**TC-HW-01 — Tạo tài sản phần cứng thành công**
- **Mức độ:** P1 — Critical.
- **Kỹ thuật:** Equivalence Partitioning / Positive Test.
- **Mục tiêu:** Xác minh một tài sản hợp lệ được lưu với tag, model và giá mua tương ứng.
- **Tiền điều kiện:** Có Model hợp lệ; cấu hình tạo tài sản và database testing sẵn sàng.
- **Dữ liệu:** `asset_tag = AST-001`; Model = Dell XPS (hoặc fixture Model tương đương); `purchase_cost = 1200`.
- **Bước thực hiện:**
  1. Tạo dữ liệu Model hợp lệ.
  2. Gửi dữ liệu tạo Asset theo đúng đường đi hiện có (Model hoặc Store Request/Feature tùy mục tiêu test).
  3. Lưu và truy vấn lại record.
  4. Kiểm tra các trường được lưu và QR/identifier path nếu có thể kiểm thử tự động.
- **Kết quả mong đợi:** Asset lưu thành công; tag/model/purchase cost đúng; record xuất hiện trong truy vấn/danh sách phù hợp; QR Code được tạo hoặc liên kết đúng theo cơ chế thực tế của ứng dụng.
- **Bằng chứng:** PHPUnit output, `assertDatabaseHas`/record state, test method và log MySQL nếu chạy K16.
- **File dự kiến:** `tests/Unit/AssetTest.php`; Feature test chỉ khi cần kiểm chứng request/list/QR.
- **Trạng thái ban đầu:** `PLANNED`.

**TC-HW-02 — Chặn tạo trùng mã tài sản**
- **Mức độ:** P1 — Critical.
- **Kỹ thuật:** Unique Constraint / Negative Test.
- **Mục tiêu:** Ngăn hai Asset đang tồn tại sử dụng cùng `asset_tag`.
- **Tiền điều kiện:** Có sẵn Asset `AST-001`.
- **Dữ liệu:** Tạo Asset thứ hai với `asset_tag = AST-001`.
- **Bước thực hiện:**
  1. Tạo và lưu Asset thứ nhất.
  2. Thử lưu Asset thứ hai có cùng tag qua đường lưu chuẩn.
  3. Kiểm tra validation/database exception theo implementation.
  4. Kiểm tra số lượng record cùng tag sau thao tác.
- **Kết quả mong đợi:** Không có hai record hợp lệ trùng tag; request/model báo lỗi phù hợp; nếu migration thực sự có UNIQUE index thì MySQL phải bảo vệ ở tầng DB khi đi đường ghi trực tiếp.
- **Lưu ý:** Không giả định trước MySQL chắc chắn phát sinh lỗi 1062. Chỉ kỳ vọng lỗi 1062 khi đã xác minh UNIQUE index tồn tại trong schema đang chạy.
- **File dự kiến:** `tests/Unit/AssetTest.php`; có thể thêm Feature test nếu cần xác minh thông báo form/request.
- **Trạng thái ban đầu:** `NEEDS VERIFICATION` (đặc biệt về UNIQUE index).

**TC-HW-03 — Xử lý va chạm mã tự tăng giữa hai phiên**
- **Mức độ:** P1 — Major theo bảng testcase của báo cáo.
- **Kỹ thuật:** Concurrency Testing.
- **Mục tiêu:** Không tạo dữ liệu trùng khi hai phiên cùng lấy hoặc lưu một mã tự tăng.
- **Tiền điều kiện:** Đã xác định chính xác hàm cấp mã và cách ứng dụng xử lý duplicate/retry; DB và test harness hỗ trợ transaction/concurrency.
- **Dữ liệu:** Hai phiên/session cùng nhận hoặc cố tình lưu một candidate tag.
- **Bước thực hiện:**
  1. Khởi tạo hai worker/session test độc lập.
  2. Điều phối để cả hai cùng đọc/tạo candidate tag trước khi commit.
  3. Cho hai session thực hiện lưu.
  4. Kiểm tra unique constraint/validation, kết quả từng session và số record cuối cùng.
- **Kết quả mong đợi:** Không có dữ liệu trùng. Một session có thể lưu; session còn lại được từ chối hoặc lấy mã mới theo đúng cơ chế hiện có. Không khẳng định có retry tự động nếu source code không triển khai.
- **File dự kiến:** `tests/Unit/AssetTest.php` hoặc integration test riêng nếu yêu cầu nhiều connection/process.
- **Trạng thái ban đầu:** `PLANNED`.

**TC-HW-04 — Tự động sinh mã khi bỏ trống Tag**
- **Mức độ:** P2 — Moderate theo bảng testcase của báo cáo.
- **Kỹ thuật:** Auto-increment / Positive Test.
- **Mục tiêu:** Xác minh hệ thống tự sinh tag khi `asset_tag` trống và cấu hình tự tăng được bật.
- **Tiền điều kiện:** Cấu hình prefix, padding và số tiếp theo được thiết lập có kiểm soát.
- **Dữ liệu:** Bỏ trống `asset_tag`; các trường bắt buộc khác hợp lệ; auto-increment bật.
- **Bước thực hiện:** Gửi dữ liệu tạo mới; kiểm tra tag được sinh trước/sau persistence; truy vấn record.
- **Kết quả mong đợi:** Tag kế tiếp đúng quy tắc cấu hình (ví dụ `AST-0002`, chỉ là ví dụ); record lưu với tag được sinh.
- **File dự kiến:** `tests/Unit/AssetTest.php` và/hoặc Feature test cho `StoreAssetRequest`.
- **Trạng thái ban đầu:** `PLANNED`.

**TC-HW-05 — Tự động bù mã số khuyết**
- **Mức độ:** P3 — Minor theo bảng testcase của báo cáo.
- **Kỹ thuật:** Gap Backfill Analysis.
- **Mục tiêu:** Xác minh thuật toán có bù khoảng trống trong dãy tag hay không, theo đúng hành vi đã được yêu cầu/mô tả.
- **Tiền điều kiện:** Đã đọc thuật toán autoincrement và xác nhận cơ chế backfill thực sự được hỗ trợ.
- **Dữ liệu:** Tạo hoặc chuẩn bị chuỗi tag có khoảng trống; ví dụ tạo số thứ tự 5 trong khi số nhỏ hơn còn thiếu.
- **Bước thực hiện:** Tạo tag nhảy cóc, sau đó yêu cầu sinh tag tiếp theo; kiểm tra lựa chọn của thuật toán.
- **Kết quả mong đợi:** Nếu implementation hỗ trợ backfill, mã trống được chọn theo đúng thuật toán. Nếu không hỗ trợ, ghi nhận `BLOCKED`/`NOT SUPPORTED` kèm bằng chứng; không tự viết thêm tính năng chỉ để đạt expected.
- **File dự kiến:** `tests/Unit/AssetTest.php`.
- **Trạng thái ban đầu:** `NEEDS VERIFICATION`.

#### NHÓM B — TS-02.2: THUẬT TOÁN KHẤU HAO ĐƯỜNG THẲNG & GIỚI HẠN SÀN

Công thức theo báo cáo:

$$\text{Monthly Depreciation} = \frac{\text{Purchase Cost} - \text{Salvage Floor}}{\text{Total Depreciation Months}}$$

$$\text{Book Value} = \max\big(\text{Purchase Cost} - (\text{Monthly Depreciation} \times \text{Months Used}),\; \text{Salvage Floor}\big)$$

**TC-DP-01 — Tính khấu hao đúng hạn giữa chu kỳ**
- **Mức độ:** P1 — Critical.
- **Kỹ thuật:** Financial Calculation / Linear Depreciation.
- **Mục tiêu:** Xác minh giá trị còn lại giữa chu kỳ khấu hao.
- **Dữ liệu:** Purchase cost `$1200`; thời gian khấu hao 12 tháng; đã sử dụng 6 tháng; floor được cấu hình rõ ràng là `$0` cho phép tính mẫu này.
- **Bước thực hiện:** Tạo fixture/đối tượng khấu hao theo API/model thật; gọi `getLinearDepreciatedValue` với mốc đã sử dụng tương ứng; kiểm tra book value và accumulated depreciation.
- **Kết quả mong đợi:** Khấu hao lũy kế `$600` (50%); giá trị sổ sách `$600.00`.
- **File dự kiến:** `tests/Unit/DepreciationTest.php`.
- **Trạng thái ban đầu:** `PLANNED`.

**TC-DP-02 — Không khấu hao dưới giá trị sàn thu hồi**
- **Mức độ:** P1 — Critical.
- **Kỹ thuật:** Boundary Value Analysis.
- **Mục tiêu:** Xác minh sau khi vượt quá thời hạn khấu hao, giá trị không thấp hơn floor và không âm.
- **Dữ liệu:** Purchase cost `$1000`; đã sử dụng 36 tháng; floor `$100` (xác nhận cấu hình ở chế độ `amount`; nếu implementation dùng `percent` thì cấu hình tương đương và ghi lại).
- **Bước thực hiện:** Khởi tạo depreciation schedule; gọi method sau khi đã sử dụng quá thời hạn; kiểm tra book value.
- **Kết quả mong đợi:** Giá trị sổ sách dừng tại `$100.00`; không về 0 hoặc số âm.
- **File dự kiến:** `tests/Unit/DepreciationTest.php`.
- **Trạng thái ban đầu:** `PLANNED`.

**TC-DP-03 — Tính tỷ lệ phần trăm khấu hao**
- **Mức độ:** P1 — Major theo bảng testcase của báo cáo.
- **Kỹ thuật:** Percentage / Ratio Testing.
- **Mục tiêu:** Xác minh tỷ lệ tiến độ/khấu hao theo thời gian đã sử dụng.
- **Dữ liệu:** Dùng 3 tháng trên chu kỳ 12 tháng.
- **Bước thực hiện:** Gọi method tính phần trăm thực tế được source code cung cấp; kiểm tra giá trị trả về/định dạng hiển thị nếu có.
- **Kết quả mong đợi:** Tỷ lệ `25.00%`.
- **Lưu ý:** Không tự tạo method mới nếu code chưa có. Nếu tỷ lệ được tính ở nơi khác, ghi đúng method/path thực tế.
- **File dự kiến:** `tests/Unit/DepreciationTest.php`.
- **Trạng thái ban đầu:** `PLANNED`.

#### NHÓM C — TS-02.3: THUỘC TÍNH PHẦN CỨNG, CUSTOM FIELDS & LINH KIỆN

**TC-HW-06 — Bắt buộc chọn dòng Model**
- **Mức độ:** P1 — Major.
- **Kỹ thuật:** Required Field / Referential Integrity.
- **Mục tiêu:** Không tạo Asset thiếu Model hoặc trỏ tới Model không hợp lệ.
- **Dữ liệu:** `model_id = null`; bổ sung ca biên với ID không tồn tại và Model đã soft-delete nếu implementation/report yêu cầu.
- **Bước thực hiện:** Gửi yêu cầu tạo Asset với các trường khác hợp lệ nhưng Model để trống/không hợp lệ; kiểm tra lỗi validation và database.
- **Kết quả mong đợi:** Request bị từ chối; không phát sinh Asset mồ côi; lỗi gắn với trường Model theo cấu trúc response thực tế.
- **File dự kiến:** `tests/Unit/AssetTest.php`; Feature test trong `tests/Feature/Assets/` nếu cần xác minh request.
- **Trạng thái ban đầu:** `PLANNED`.

**TC-HW-07 — Kiểm tra biên hạn bảo hành**
- **Mức độ:** P2 — Moderate.
- **Kỹ thuật:** Boundary Value Analysis.
- **Mục tiêu:** Kiểm tra miền giá trị `warranty_months` từ 0 đến 240 tháng.
- **Dữ liệu:** `-1`, `0`, `240`, `241`.
- **Bước thực hiện:** Chạy từng giá trị độc lập; kiểm tra validation response và trạng thái persistence.
- **Kết quả mong đợi:** `-1` và `241` bị từ chối; `0` và `240` được chấp nhận.
- **Lưu ý:** Không đổi expected theo hành vi lỗi hiện tại. Nếu `241` đang pass, ghi nhận defect và RCA; việc sửa rule chỉ thực hiện sau khi xác minh yêu cầu và phạm vi.
- **File dự kiến:** `tests/Unit/AssetTest.php` và Feature test nếu cần.
- **Trạng thái ban đầu:** `NEEDS VERIFICATION`.

**TC-HW-08 — Chuẩn hóa giá mua có dấu phân cách**
- **Mức độ:** P2 — Moderate.
- **Kỹ thuật:** Input Format / Data Normalization.
- **Mục tiêu:** Xác minh chuỗi giá mua được parse chính xác trước khi persistence.
- **Dữ liệu:** `25.500.000` và `25,500,000.50`.
- **Bước thực hiện:** Gửi từng chuỗi qua đường xử lý request thực tế; xác minh giá trị sau `prepareForValidation`/helper và giá trị lưu DB.
- **Kết quả mong đợi:** Giá trị số tương ứng `25500000.00` và `25500000.50` (nếu quy ước locale/parser của dự án xác nhận cách hiểu này).
- **Lưu ý:** Xác định locale và parser trước khi chốt expected; tránh kiểm thử bằng cách gọi helper không đi qua đường xử lý thật nếu mục tiêu là request normalization.
- **File dự kiến:** Feature test tạo Asset; có thể đặt trong `tests/Feature/Assets/`.
- **Trạng thái ban đầu:** `PLANNED`.

**TC-HW-09 — Mã hóa và bảo vệ Custom Fields nhạy cảm (IMEI/MAC)**
- **Mức độ:** P1 — Critical.
- **Kỹ thuật:** Data Protection / Authorization Boundary.
- **Mục tiêu:** Xác minh dữ liệu Custom Field được mã hóa khi cấu hình encrypted; người đủ quyền xem nhận giá trị đã giải mã, người không đủ quyền không đọc được dữ liệu nhạy cảm.
- **Dữ liệu:** IMEI mẫu `358764091234567`; MAC mẫu có định dạng hợp lệ theo rule thực tế; Custom Field bật encrypted.
- **Bước thực hiện:**
  1. Tạo/chuẩn bị Custom Field với cờ encrypted bật.
  2. Lưu giá trị IMEI/MAC qua luồng ứng dụng hỗ trợ.
  3. Kiểm tra giá trị vật lý trong DB không bằng plaintext.
  4. Kiểm tra người có gate/permission `assets.view.encrypted_custom_fields` (nếu đúng là gate đang dùng) đọc lại đúng plaintext.
  5. Kiểm tra người không có quyền không được tiết lộ plaintext qua response/view.
  6. Kiểm tra định dạng MAC và định dạng IMEI theo cấu hình thật; không tự giả định có predefined IMEI format.
- **Kết quả mong đợi:** Dữ liệu lưu an toàn; giải mã đúng khi được phép; không rò rỉ plaintext khi không có quyền.
- **File dự kiến:** Unit/Feature test hiện có liên quan tới `CustomField`/`StoreAsset`; chỉ bổ sung file mới nếu chưa có coverage tương đương.
- **Trạng thái ban đầu:** `NEEDS VERIFICATION`.

**TC-HW-10 — Tự động cộng dồn giá trị linh kiện gắn trong**
- **Mức độ:** P2 — Major.
- **Kỹ thuật:** Relationship Aggregation / Calculation.
- **Mục tiêu:** Xác minh tổng giá trị Component được tính vào giá trị Asset theo hành vi hiện có.
- **Dữ liệu:** Laptop `$1000`; hai RAM, mỗi thanh `$50`.
- **Bước thực hiện:** Chuẩn bị quan hệ Asset–Component phù hợp; đọc giá trị tổng theo accessor/method thực tế; so sánh kết quả.
- **Kết quả mong đợi:** Tổng hiển thị `$1100.00` nếu quy tắc cộng dồn trong source code xác định như ví dụ báo cáo.
- **Ranh giới:** Không kiểm thử hoặc thay đổi logic nhập/xuất/điều chỉnh tồn kho Component thuộc Phân vùng 3.
- **File dự kiến:** `tests/Unit/AssetTest.php`.
- **Trạng thái ban đầu:** `PLANNED`.

**TC-HW-11 — Xóa mềm và khôi phục tài sản phần cứng**
- **Mức độ:** P2 — Major.
- **Kỹ thuật:** Lifecycle / Soft Delete.
- **Mục tiêu:** Xác minh xóa mềm không xóa vĩnh viễn tài sản và restore đưa record trở lại truy vấn thông thường.
- **Dữ liệu:** Một Asset fixture được tạo trong test; không phụ thuộc cứng vào ID 50 của môi trường.
- **Bước thực hiện:** Tạo Asset; gọi `delete`; kiểm tra `deleted_at`; truy vấn mặc định và truy vấn `withTrashed` nếu hỗ trợ; gọi `restore`; truy vấn lại.
- **Kết quả mong đợi:** Sau delete, `deleted_at` khác null và record không xuất hiện trong query mặc định; sau restore, `deleted_at` trở lại null và record xuất hiện bình thường.
- **File dự kiến:** `tests/Unit/AssetTest.php`; Feature test chỉ khi cần kiểm chứng endpoint restore.
- **Trạng thái ban đầu:** `PLANNED`.

### 3.4. Phạm vi kiểm thử bổ sung cho AssetModel

Báo cáo phân công yêu cầu thực hiện `tests/Unit/AssetModelTest.php` và phân tích lớp `AssetModel`, nhưng danh sách TC-HW/TC-DP không đánh mã riêng các ca `AssetModel`. Vì vậy, kế hoạch bổ sung tối đa năm kiểm tra đặc trưng, sau khi kiểm tra source code:

| Mã nội bộ | Mục tiêu khảo sát/kiểm thử | Điều kiện chốt expected |
|---|---|---|
| `AM-01` | Xác minh quan hệ Eloquent tới `Depreciation` hoặc model liên quan | Chỉ test quan hệ thực sự được khai báo trong `AssetModel` |
| `AM-02` | Xác minh truy vấn Asset theo Model | Dùng relation hoặc query path thực tế; không tự tạo relation mới |
| `AM-03` | Kiểm tra phép tính/tỷ lệ liên quan tới Model (ví dụ `percentRemaining()` nếu tồn tại) | Đối chiếu đúng định nghĩa nghiệp vụ trong source code |
| `AM-04` | Kiểm tra trường hợp biên như mẫu số bằng 0 cho phép tính tỷ lệ | Chỉ áp dụng nếu method tương ứng tồn tại |
| `AM-05` | Kiểm tra `SoftDeletes` và tính toàn vẹn quan hệ của `AssetModel` | Xác minh hành vi cascade/restrict thực tế; không suy đoán |

Các ca AM-01..05 không thay thế 14 testcase bắt buộc và không được dùng để mở rộng sang checkout, license hoặc RBAC. Tên method cuối cùng sẽ được ghi vào bảng mapping sau khi khảo sát repository.

---

## 4. KẾ HOẠCH TRIỂN KHAI MÃ KIỂM THỬ TỰ ĐỘNG

### 4.1. Cấu trúc test dự kiến

| File | Nội dung phụ trách | Quy tắc |
|---|---|---|
| `tests/Unit/AssetTest.php` | TC-HW-01..07, TC-HW-10, TC-HW-11 theo mức phù hợp | Ưu tiên bổ sung vào file hiện có; không tạo method trùng |
| `tests/Unit/DepreciationTest.php` | TC-DP-01..03 và các boundary test khấu hao cần thiết | Unit test phép tính, kiểm tra số thập phân và floor |
| `tests/Unit/AssetModelTest.php` | AM-01..05 sau khảo sát | Chỉ test API/relationship thực tế của `AssetModel` |
| `tests/Feature/Assets/` | TC-HW-06..09, TC-HW-11 nếu cần qua request/API/UI | Chỉ tạo Feature test khi Unit Test không xác minh được request/persistence/authorization |
| `tests/Feature/AssetModels/` | Feature test Model nếu có luồng người dùng cần xác minh | Không tạo chỉ để tăng số lượng file |

Đường dẫn và tên file là mục tiêu tham chiếu theo báo cáo. Trước khi tạo mới, tìm test tương đương đang có để tránh trùng.

### 4.2. Nguyên tắc thiết kế test code

- Test độc lập, không phụ thuộc thứ tự chạy hoặc dữ liệu còn sót từ test khác.
- Dùng Factory/fixture sẵn có; không hardcode ID cố định nếu không cần thiết.
- Dùng `RefreshDatabase`/transaction theo base test hiện hữu; không tự thay đổi cấu hình suite nếu chưa phân tích ảnh hưởng.
- Kiểm tra cả positive, negative và boundary cases.
- Assertions phải kiểm tra kết quả nghiệp vụ, không chỉ kiểm tra method không ném exception.
- Test tiền tệ cần so sánh theo precision/decimal thực tế của DB/model; không để sai số float tạo flaky test.
- Test mã hóa phải kiểm tra ciphertext khác plaintext và kiểm tra response/view đối với actor có/không có quyền.
- Test concurrency cần xác định khả năng mở nhiều connection/process. Nếu không thể thực hiện đúng trong PHPUnit hiện tại, đánh dấu giới hạn và ghi bằng chứng, không giả lập concurrency bằng hai lần gọi tuần tự.
- Không sửa logic production nếu chưa có failing test tái hiện lỗi và chưa xác định root cause.
- Không thay đổi schema/migration chỉ để test pass; mọi đề xuất schema phải có căn cứ và được giới hạn rõ ràng.

### 4.3. Quy trình phát triển từng Test Case

Với từng TC-HW/TC-DP:

1. Đọc method, model, request, migration và test hiện có.
2. Xác định test hiện có đã bao phủ toàn bộ expected hay mới chỉ trùng tên.
3. Chốt dữ liệu, fixture, assertion và loại test.
4. Viết/cập nhật duy nhất testcase đang xử lý.
5. Chạy testcase đó.
6. Nếu fail, phân loại lỗi test setup, môi trường, expected sai, hoặc bug production; lưu output và phân tích.
7. Chạy regression liên quan.
8. Review diff và stage đúng file thuộc testcase.
9. Commit testcase riêng theo Mục 8.
10. Cập nhật bảng tiến độ và bằng chứng của testcase đó.

### 4.4. Quy tắc phạm vi sửa code

- Ưu tiên chỉ sửa file test.
- Chỉ sửa production code trong phạm vi `Asset`, `AssetModel`, `Depreciation`/`Depreciable`, `CustomField`, `StoreAssetRequest` hoặc helper liên quan khi testcase chứng minh bug và việc sửa là cần thiết.
- Không sửa `composer.json`, `composer.lock`, phiên bản PHP/Laravel/PHPUnit hoặc cấu hình PHPUnit nếu không có blocker được chứng minh và phê duyệt rõ.
- Không tạo `phpunit.part2.xml`, `.env.testing` hoặc file cấu hình mới nếu repository đã có cấu hình phù hợp.
- Không tạo bản sao `.3`, `.4`, `.5`, `.new`, `.final`, `.copy`.

---

## 5. QUY TRÌNH THỰC THI, THU THẬP BẰNG CHỨNG VÀ ĐO LƯỜNG

### 5.1. Kiểm tra môi trường đầu vào

Trước khi chạy test, ghi lại:

- `git rev-parse --show-toplevel`, `git branch --show-current`, `git status --short`.
- `git remote -v` và trạng thái đồng bộ `origin/main` (không tự ý ghi đè thay đổi local).
- `php -v`, phiên bản PHP constraint trong `composer.json`.
- `composer show phpunit/phpunit` hoặc lệnh phù hợp với dependency hiện có.
- Phiên bản Laravel, trạng thái dependencies và cấu hình DB testing.
- Docker daemon và khả năng chạy container nếu test K16 được thực hiện.
- File `AGENTS.md`, `phpunit.xml` và script Testcontainers hiện có.

Nếu môi trường không đủ điều kiện, ghi `BLOCKED` kèm output cụ thể; không tự cài hoặc nâng/hạ dependency.

### 5.2. Tầng 1 — Local Fast Feedback

Chạy các test độc lập bằng cấu hình testing hiện có. Ví dụ lệnh tham chiếu (phải điều chỉnh theo script/repository thực tế):

```powershell
php artisan test tests/Unit/AssetTest.php
php artisan test tests/Unit/DepreciationTest.php
php artisan test tests/Unit/AssetModelTest.php
```

Chạy thêm các Feature test có liên quan sau khi đã xác minh đường dẫn:

```powershell
php artisan test tests/Feature/Assets
php artisan test tests/Feature/AssetModels
```

Không dùng lệnh thư mục nếu trong đó có test ngoài phạm vi mà việc chạy có thể ảnh hưởng hoặc mất nhiều thời gian; có thể chạy từng file cụ thể.

### 5.3. Tầng 2 — Kiểm thử Docker/K16 với MySQL 8.0.36 Testcontainers

#### 5.3.1. Mục tiêu và mô hình thực thi Docker

Đây là tầng xác minh tích hợp bắt buộc đối với các kiểm thử phụ thuộc hành vi MySQL. Sử dụng Docker Desktop/Docker Engine để Testcontainers khởi tạo một MySQL tạm thời, cô lập cho phiên kiểm thử; không sử dụng một MySQL local cố định thay cho container K16.

Theo kiến trúc runner được mô tả trong báo cáo giữa kỳ:

- `tests/testcontainers/run.mjs` điều phối phiên kiểm thử.
- Runner khởi tạo container MySQL, báo cáo yêu cầu MySQL `8.0.36`.
- Runner chuẩn bị cấu hình kết nối testing (bao gồm `.env.testing` nếu cơ chế hiện hữu cần tạo), truyền thông tin kết nối động cho PHPUnit.
- PHPUnit chạy migration/schema trên database sạch, sau đó thực thi test file hoặc `--filter` được truyền vào runner.
- Khối `finally` của runner dừng và xóa container kể cả khi test PASS hoặc FAIL; runner cũng dọn `.env.testing` nếu chính runner tạo file đó.
- Runner trả lại exit code của PHPUnit để người thực hiện/CI xác định kết quả.

Làm rõ khái niệm “chạy test trên Docker”: với luồng Testcontainers mặc định được mô tả ở trên, MySQL chạy trong Docker container, còn tiến trình Node/PHPUnit có thể chạy trên máy host và kết nối tới MySQL qua cổng được ánh xạ động. Đây vẫn là kiểm thử tích hợp trên Docker/MySQL bằng K16. Không được báo cáo rằng toàn bộ PHP application/test process chạy bên trong container nếu chưa dùng và xác minh chế độ Dockerized test runner riêng.

Báo cáo giữa kỳ còn mô tả phương án thay thế `tests/Run-Testcontainers.ps1` kết hợp `tests/Run-Testcontainers.php`, chạy test trong image `snipe/snipe-it` và giao tiếp với Docker host qua `docker.sock`. Chỉ sử dụng phương án này khi các script đó thực sự tồn tại và được Infrastructure Lead xác nhận còn hỗ trợ; không tự cài đặt hoặc chỉnh sửa runner chung trong phạm vi Phân vùng 2.

#### 5.3.2. Điều kiện tiên quyết Docker

Trước mỗi đợt chạy K16, kiểm tra và lưu output:

```powershell
docker version
docker info
docker ps -a
node --version
npm --version
php -v
composer show phpunit/phpunit
```

Sau đó khảo sát runner và npm script hiện có, không sửa file hạ tầng:

```powershell
Get-Content package.json
Get-Content tests/testcontainers/run.mjs
npm run
```

Checklist trước khi chạy:

- Docker Desktop/Docker Engine đã khởi động và Docker daemon có thể truy cập.
- Image MySQL `8.0.36` có thể pull hoặc đã có trong local image cache; không tự đổi sang `latest`.
- Node.js/npm và dependencies cần thiết của runner đã sẵn sàng.
- PHP/Composer dependencies và `vendor/bin/phpunit` sẵn sàng nếu runner gọi PHPUnit trên host.
- Các biến môi trường/testing config được runner quản lý theo đúng implementation hiện có.
- Working tree, branch và commit hash đã được ghi nhận; không chạy test destructive trên database thật.
- Không có container test cũ bị bỏ lại từ phiên trước; nếu phát hiện phải ghi nhận và xác minh owner trước khi dọn.
- Không tự chạy `docker system prune`, xóa volume/image dùng chung hoặc kill container không xác định nguồn gốc. Không tự tạo `.env.testing` thủ công nếu runner tự quản lý file này.

Nếu Docker daemon không hoạt động, ghi `BLOCKED — DOCKER DAEMON UNAVAILABLE` kèm output `docker version`/`docker info`; không đổi sang SQLite rồi tuyên bố đã hoàn thành K16.

#### 5.3.3. Lệnh chạy Docker/K16

Cú pháp chuẩn được báo cáo giữa kỳ mô tả:

```powershell
npm run test:integration:testcontainers -- <đường dẫn file test hoặc --filter>
```

Trước khi dùng, xác minh `package.json` thực sự khai báo script `test:integration:testcontainers` và `run.mjs` chuyển tiếp tham số tới PHPUnit. Nếu script hoặc cú pháp khác, dùng đúng cú pháp đang có và ghi lại lệnh thực tế; không tự tạo npm script thay thế.

Chạy lần lượt từng bộ test chính trên MySQL container:

```powershell
npm run test:integration:testcontainers -- tests/Unit/AssetTest.php
npm run test:integration:testcontainers -- tests/Unit/DepreciationTest.php
npm run test:integration:testcontainers -- tests/Unit/AssetModelTest.php
```

Để kiểm tra đúng từng testcase trong vòng lặp commit độc lập, dùng `--filter` với tên method đã xác minh trong source test, ví dụ mẫu:

```powershell
npm run test:integration:testcontainers -- tests/Unit/AssetTest.php --filter=<TEN_METHOD_TC_HW_XX>
npm run test:integration:testcontainers -- tests/Unit/DepreciationTest.php --filter=<TEN_METHOD_TC_DP_XX>
npm run test:integration:testcontainers -- tests/Unit/AssetModelTest.php --filter=<TEN_METHOD_ASSET_MODEL>
```

`<TEN_METHOD_...>` là placeholder, phải thay bằng method thực tế; không copy nguyên placeholder để chạy. Nếu testcase nằm trong Feature test, truyền đúng đường dẫn Feature test đã được xác minh, ví dụ `tests/Feature/Assets/...`; không tạo file Feature mới chỉ vì ví dụ này.

Sau mỗi testcase: chạy filter trên MySQL container nếu test tương thích; sau đó chạy regression file/suite liên quan trên container trước khi chốt commit. Cuối giai đoạn phải chạy lại toàn bộ ba file Unit chính trên MySQL container, không chỉ dựa vào các lần chạy filter rời rạc.

#### 5.3.4. Ma trận bắt buộc chạy trên Docker/MySQL

| Test Case / nhóm | Kiểm tra cụ thể trong Docker/MySQL | Tiêu chí ghi nhận |
|---|---|---|
| **TC-HW-01** | Migration, tạo Asset, persistence, đọc lại tag/model/cost; QR path nếu test được | Record tồn tại và dữ liệu đúng trên MySQL container |
| **TC-HW-02** | Validation trùng tag; kiểm tra schema/index; thử DB-level insert chỉ khi có mục tiêu xác minh UNIQUE index | Ghi rõ chặn ở model/request hay DB; không mặc định có lỗi 1062 |
| **TC-HW-03** | Hai connection/session đồng thời cùng candidate tag nếu harness cho phép | Không có duplicate; nếu không thể đồng thời thật thì BLOCKED/NOT RUN, không giả lập tuần tự |
| **TC-HW-04** | Tự sinh tag qua Store Request/logic thật và xác minh giá trị đã lưu | Tag được sinh đúng prefix/sequence/padding cấu hình |
| **TC-HW-05** | Chuỗi tag có gap, kiểm tra thuật toán cấp mã khi lưu trên MySQL | Chỉ PASS nếu implementation hiện có thực sự backfill đúng yêu cầu |
| **TC-DP-01** | Chạy test tính toán trong runner K16; nếu có persistence/relationship thì truy vấn dữ liệu trên MySQL | Giá trị giữa chu kỳ khớp expected và precision được định nghĩa |
| **TC-DP-02** | Chạy tình huống vượt thời hạn khấu hao trên runner K16 | Book value không thấp hơn floor, không âm |
| **TC-DP-03** | Chạy phép tính tỷ lệ trên runner K16 | Kết quả `3/12 = 25.00%` theo cách biểu diễn thực tế |
| **TC-HW-06** | Tạo Asset với model null/không tồn tại/model soft-deleted qua đường lưu thật | Request/model từ chối và không tạo record mồ côi |
| **TC-HW-07** | Thực hiện lần lượt `-1`, `0`, `240`, `241` và kiểm tra persistence | `-1`/`241` bị từ chối; `0`/`240` được chấp nhận theo yêu cầu báo cáo |
| **TC-HW-08** | Parse chuỗi tiền và xác minh giá trị persisted trên MySQL | Không mất phần thập phân, không nhân/chia sai theo locale |
| **TC-HW-09** | Chạy migration Custom Field, lưu ciphertext, authorized/unauthorized read | Dữ liệu không plaintext trong storage và quyền xem được áp dụng đúng |
| **TC-HW-10** | Nạp Asset/Component fixture cần thiết và kiểm tra phép cộng giá trị quan hệ | Tổng kết quả đúng theo logic aggregation hiện có; không kiểm tra tồn kho Phân vùng 3 |
| **TC-HW-11** | Delete, truy vấn thường, restore và truy vấn lại trên MySQL | `deleted_at`/scope/restore đúng, không mất record |
| **AM-01..05** | Chạy các kiểm tra relation/query của `AssetModel` với migration và fixture trên MySQL | Quan hệ/truy vấn đúng với implementation thực tế |

Một testcase thuần tính toán vẫn có thể được chạy qua runner K16 để xác minh tương thích suite/config trên MySQL. Tuy nhiên, nếu nó không truy cập DB thì phải mô tả đúng là “PHPUnit chạy qua K16 runner trong phiên có MySQL container”, không được tuyên bố testcase đó đã kiểm tra DB persistence.

#### 5.3.5. Vòng đời container và kiểm tra teardown

Trong mỗi lần chạy K16, ghi nhận theo trình tự:

1. Snapshot `docker ps -a` trước khi chạy để nhận diện container đang tồn tại; không xóa container không thuộc phiên test.
2. Ghi command đầy đủ, working directory, branch và commit hash.
3. Chạy runner; lưu stdout/stderr và exit code. Nếu runner in container ID, image, mapped port hoặc trạng thái readiness thì giữ lại các dòng log đó.
4. Xác nhận runner đã hoàn thành migration và PHPUnit, không chỉ xác nhận container đã khởi động.
5. Khi runner kết thúc, kiểm tra `docker ps -a` lần nữa và đối chiếu container do Testcontainers tạo đã được dừng/xóa. Nếu runner tự remove container nên không còn container để inspect, dùng log lifecycle/teardown của runner làm bằng chứng.
6. Xác minh `.env.testing` được cleanup nếu runner tạo file này; không xóa file nếu đó là file có sẵn của người dùng/repository.
7. Ghi nhận exit code: `0` chỉ chứng minh lệnh kết thúc thành công; vẫn phải đọc PHPUnit summary để phân biệt tests, assertions, skipped/incomplete.
8. Không giữ lại MySQL container/volume cho lần chạy sau nếu quy trình K16 thiết kế container ephemeral. Không chạy `docker rm -f` theo tên phỏng đoán; chỉ xử lý tài nguyên có thể xác minh thuộc phiên test hiện tại.

#### 5.3.6. Ma trận kết quả SQLite và Docker phải tách riêng

Mỗi testcase có hai cột kết quả môi trường độc lập:

- **SQLite/local:** dùng fast feedback nếu testcase tương thích.
- **Docker/MySQL K16:** bằng chứng tích hợp trên MySQL container.

Không suy diễn SQLite PASS thành Docker PASS; cũng không suy diễn Docker PASS nếu chỉ chạy PHPUnit trên SQLite trong khi Docker đang mở. Những ca không tương thích SQLite có thể ghi `N/A` ở SQLite và vẫn chạy MySQL. Những ca không chạy được Docker phải để `BLOCKED` hoặc `NOT RUN` với nguyên nhân cụ thể.

#### 5.3.7. Phương án Dockerized test runner thay thế

Nếu yêu cầu của đợt nghiệm thu là PHP/PHPUnit cũng phải chạy bên trong Docker container (không chỉ MySQL), kiểm tra phương án đã mô tả trong báo cáo:

- `tests/Run-Testcontainers.ps1`
- Kết hợp `tests/Run-Testcontainers.php` và image `snipe/snipe-it` theo hướng dẫn thực tế trong repository. Chỉ dùng khi script tồn tại, đọc rõ cách truyền file/filter và được xác nhận tương thích. Cơ chế này dùng Docker socket của host (`docker.sock`), vì vậy phải đánh giá quyền truy cập và phạm vi mount trước khi chạy.

Đây là chế độ khác với runner Node.js mặc định. Báo cáo phải ghi rõ chế độ nào được sử dụng:

- **`K16-DB-CONTAINER`:** PHPUnit/Node chạy trên host, MySQL chạy trong ephemeral Docker container.
- **`K16-TEST-RUNNER-CONTAINER`:** PHP/PHPUnit và MySQL được điều phối/chạy qua Docker theo script thay thế.

Không ghi cả hai chế độ đã chạy nếu không có output tương ứng.

### 5.4. Bằng chứng phải thu thập

Mỗi lần chạy SQLite hoặc Docker phải ghi riêng:

- Lệnh đầy đủ và working directory.
- Branch, commit hash và `git status --short` tại thời điểm chạy.
- Phiên bản PHP, PHPUnit, Laravel, Node.js, npm, Docker client/server và MySQL image thực tế.
- Chế độ thực thi (`SQLite/local`, `K16-DB-CONTAINER` hoặc `K16-TEST-RUNNER-CONTAINER`).
- Số tests, assertions, passed, failed, skipped, incomplete, errors và exit code.
- Thời gian thực thi.
- Log failure/stack trace; không chỉ chụp dòng cuối PASS.
- Kết quả migration và truy vấn DB liên quan.
- Kết quả schema/index inspection khi xác minh `asset_tag`.
- Kết quả truy vấn DB cho các test persistence/custom fields/soft delete.
- Image tag MySQL, container ID/lifecycle log, readiness và bằng chứng teardown.
- Snapshot Docker trước/sau khi chạy khi cần chứng minh không để lại container rác.
- Nếu CI workflow đã có hỗ trợ K16, có thể đính kèm URL/run ID và artifact của workflow làm bằng chứng bổ sung; không thay thế log chạy local khi yêu cầu nghiệm thu tại máy local.

### 5.5. Chỉ số chất lượng cần báo cáo

| Metric | Mục tiêu / cách đánh giá | Nguồn bằng chứng |
|---|---|---|
| **Test Case Completion** | 14/14 testcase chính có trạng thái cuối cùng rõ ràng; không ẩn ca blocked | Ma trận testcase |
| **Test Pass Rate** | Báo cáo tỷ lệ thực tế; mục tiêu nghiệm thu là 100% các test được xác định là runnable sau khi xử lý defect | PHPUnit output |
| **Assertions** | Ghi tổng số assertion thực tế theo suite/file; không đặt con số tùy tiện thay cho chất lượng assertion | PHPUnit summary |
| **Depreciation correctness** | Bao phủ giữa kỳ, floor, hết hạn/quá hạn và tỷ lệ | `DepreciationTest.php` output |
| **DB integrity** | Có bằng chứng schema/index và persistence trên MySQL cho các ca yêu cầu | MySQL log/query |
| **Sensitive data protection** | Chứng minh ciphertext, authorized read và unauthorized disclosure prevention | Test output + DB assertions |
| **Regression** | Các test liên quan không bị ảnh hưởng sau từng commit | Regression output |
| **Container teardown** | Container được dừng/xóa theo runner, không để tài nguyên rác | Runner log / Docker state |
| **Coverage** | Thu thập nếu project đã có Xdebug/PCOV; báo cáo số liệu thực tế, không đặt % khi chưa có công cụ | Coverage report |

Không sử dụng mục tiêu assertions/coverage của Phân vùng 4 như chỉ tiêu mặc định cho Phân vùng 2. Chỉ ghi coverage khi môi trường thực sự hỗ trợ đo.

---

## 6. QUẢN LÝ LỖI VÀ PHÂN TÍCH NGUYÊN NHÂN GỐC RỄ

### 6.1. Phân loại Severity cho Phân vùng 2

| Severity | Mô tả theo nghiệp vụ Phân vùng 2 | Ví dụ |
|---|---|---|
| **Blocker** | Mất hoặc hỏng dữ liệu tài sản diện rộng; có thể tạo dữ liệu sai không thể khôi phục | Ghi đè/xóa nhầm dữ liệu Asset hàng loạt |
| **Critical** | Vi phạm định danh hoặc giá trị tài chính/bảo mật nghiêm trọng | Tag trùng được ghi nhận; giá trị khấu hao âm; plaintext IMEI/MAC bị lộ |
| **Major** | Quy tắc nghiệp vụ quan trọng sai nhưng có phạm vi ảnh hưởng giới hạn | Tính sai giá trị sổ sách/floor; cho tạo Asset thiếu Model hợp lệ |
| **Minor** | Sai lệch ít ảnh hưởng, thông báo/định dạng không nhất quán | Format thông báo lỗi hoặc hiển thị tỷ lệ không đúng định dạng |

Severity là mức ảnh hưởng; Priority thể hiện thứ tự xử lý. Phải ghi riêng hai giá trị nếu khác nhau.

### 6.2. Quy trình Defect Management

1. Ghi nhận testcase, môi trường, branch và commit tái hiện.
2. Lưu nguyên văn lệnh test, actual result, expected result và stack trace/log.
3. Xác định lỗi thuộc test data, test harness, môi trường, expected chưa đúng nguồn hay production code.
4. Thực hiện RCA (5 Whys hoặc phân tích theo call path/schema).
5. Đề xuất sửa trong phạm vi; đánh giá regression và ảnh hưởng module khác.
6. Viết test hồi quy tái hiện lỗi trước khi sửa nếu có thể.
7. Chạy lại testcase và regression sau sửa.
8. Commit riêng theo testcase; không gộp defect thuộc testcase khác.

### 6.3. Mẫu Defect Report

```markdown
### [DEFECT-HW/DP-XX] Tiêu đề ngắn gọn
- **Người phát hiện:** Nguyễn Văn Quốc (2312729)
- **Phân vùng:** 2 — Hardware Asset / Asset Model / Depreciation
- **Test Case liên quan:**
- **Severity / Priority:**
- **Branch / Commit:**
- **Môi trường:** PHP / PHPUnit / SQLite hoặc MySQL 8.0.x
- **Preconditions / Test Data:**
- **Steps to Reproduce:**
  1.
  2.
- **Expected Result:**
- **Actual Result:**
- **Log / Evidence:**
- **Root Cause Analysis:**
- **Proposed Fix:**
- **Regression Tests:**
- **Resolution / Commit Hash:**
```

### 6.4. Các điểm cần RCA ưu tiên

- Schema có thực sự có UNIQUE index cho `assets.asset_tag` hay không; validation có bảo vệ soft-deleted records theo đúng rule không.
- Cơ chế tự tăng có transaction/locking/retry không; collision được xử lý ở đâu.
- `getLinearDepreciatedValue` có xử lý floor amount/percent, 0 tháng, đủ hạn và quá hạn chính xác không.
- Rule `warranty_months` có kiểm tra đúng miền số học 0–240 không.
- `ParseCurrency()` có parse đúng theo locale và tránh sai lệch thập phân không.
- Custom Field encrypted có thực sự mã hóa ở storage và gate kiểm soát mọi đường trả dữ liệu không.

---

## 7. LỘ TRÌNH TRIỂN KHAI VÀ TIÊU CHÍ NGHIỆM THU

### 7.1. Roadmap theo giai đoạn

| Giai đoạn | Công việc | Đầu ra / điều kiện qua bước |
|---|---|---|
| **G0 — Repository Audit** | Xác minh repository, branch, remote, working tree, AGENTS, PHP, PHPUnit, migrations và cấu hình test | Không ghi đè thay đổi hiện có; có báo cáo preflight |
| **G1 — Planning** | Hoàn thiện tài liệu kế hoạch này, đối chiếu yêu cầu report và mapping testcase | File kế hoạch được review và commit riêng |
| **G2 — TS-02.1** | Triển khai TC-HW-01..05 theo thứ tự và phụ thuộc | Từng TC được chạy, review, commit riêng hoặc ghi blocker |
| **G3 — TS-02.2** | Triển khai TC-DP-01..03 | Công thức, floor và tỷ lệ được xác minh |
| **G4 — TS-02.3** | Triển khai TC-HW-06..11 | Validation, Custom Fields, aggregation, lifecycle được kiểm chứng |
| **G5 — AssetModel** | Hoàn thiện AM-01..05 theo source thực tế | `AssetModelTest.php` có coverage dựa trên relation/method thật |
| **G6 — Local Regression** | Chạy toàn bộ Unit/Feature test thuộc phạm vi | Có summary PHPUnit và danh sách blocker |
| **G7 — Docker/K16 Verification** | Chạy từng testcase phù hợp và toàn bộ suite Phân vùng 2 qua runner Testcontainers với MySQL `8.0.36`; dùng Dockerized test runner thay thế nếu phạm vi nghiệm thu yêu cầu PHP/PHPUnit cũng chạy trong container và script được xác minh | Có output PHPUnit, image/version, migration, log vòng đời và teardown; tách riêng SQLite và Docker results |
| **G8 — Final Review** | Review diff, lịch sử commit, status, file ngoài phạm vi và báo cáo | Đạt DoD hoặc liệt kê rõ phần chưa đạt |

### 7.2. Thứ tự triển khai chi tiết

1. Preflight và xác minh dependency.
2. Đọc source/migration và xác minh các điểm mâu thuẫn trước khi chốt test expected.
3. Commit file kế hoạch.
4. `TC-HW-01` → `TC-HW-02` → `TC-HW-03` → `TC-HW-04` → `TC-HW-05`.
5. `TC-DP-01` → `TC-DP-02` → `TC-DP-03`.
6. `TC-HW-06` → `TC-HW-07` → `TC-HW-08` → `TC-HW-09` → `TC-HW-10` → `TC-HW-11`.
7. `AM-01..AM-05` sau khi khảo sát `AssetModel`.
8. Chạy regression toàn bộ Phân vùng 2 trên local DB.
9. Chạy từng testcase cần MySQL bằng Docker/Testcontainers; chạy lại toàn bộ `AssetTest.php`, `DepreciationTest.php`, `AssetModelTest.php` qua K16 runner với MySQL `8.0.36`.
10. Nếu yêu cầu kiểm thử là toàn bộ PHP/PHPUnit phải ở trong Docker, xác minh và chạy thêm chế độ `tests/Run-Testcontainers.ps1`/`.php` nếu repository hỗ trợ; báo cáo riêng chế độ này.
11. Xác minh container teardown, thu log/metrics và hoàn thiện tài liệu kết quả, Defect/RCA, lịch sử commit.

Có thể điều chỉnh thứ tự nếu có dependency kỹ thuật được chứng minh; phải ghi lý do trong bảng theo dõi.

### 7.3. Definition of Done (DoD)

Một testcase chỉ được coi là hoàn tất khi:

- Expected Result đối chiếu với báo cáo và source code thực tế.
- Có test tự động hoặc có lý do rõ ràng vì sao không thể tự động hóa.
- Test đã được chạy bằng lệnh cụ thể và có output.
- Assertions xác minh kết quả nghiệp vụ, không chỉ xác minh code chạy không lỗi.
- Đã chạy regression có liên quan.
- Đã review diff và xác nhận không có thay đổi ngoài phạm vi.
- Đã chạy kiểm chứng Docker/K16 với MySQL `8.0.36` cho các testcase cần DB và đã chạy regression suite trên container; testcase blocked phải có bằng chứng blocker.
- Nếu tiêu chí nghiệm thu yêu cầu PHP/PHPUnit chạy trong container, đã chạy chế độ Dockerized test runner và có output riêng; không đánh đồng chế độ này với MySQL-only container.
- Đã xác minh teardown và ghi lại Docker/test runner logs.
- Đã commit riêng testcase trên branch được phê duyệt.
- Bảng tiến độ ghi tách trạng thái SQLite/Docker, test output và commit hash.

Nghiệm thu toàn Phân vùng 2 khi:

- Có trạng thái cuối cùng cho đủ 14 testcase chính và các ca `AssetModel` được chốt.
- Không che giấu test FAIL/BLOCKED/NOT RUN.
- Có kết quả thực tế của `AssetTest.php`, `DepreciationTest.php`, `AssetModelTest.php`.
- Những test DB-dependent có kết quả MySQL thật hoặc ghi rõ lý do chưa chạy.
- Có báo cáo schema/index cho `asset_tag` và test encryption cho Custom Fields.
- Không làm thay nhiệm vụ phân vùng khác; không sửa runner/hạ tầng chung ngoài phạm vi.
- Lịch sử Git có commit kế hoạch riêng và mỗi testcase hoàn tất có commit riêng.
- Working tree sạch sau khi kết thúc; không push, không PR, không merge nếu chưa có yêu cầu mới.

---

## 8. QUY TRÌNH GIT VÀ CHIẾN LƯỢC COMMIT TỪNG TEST CASE

### 8.1. Preflight Git bắt buộc

Trước mọi thao tác tạo branch/stage/commit:

```powershell
git rev-parse --show-toplevel
git remote -v
git branch --show-current
git status --short
git fetch origin
git log --oneline --decorate -5 origin/main
```

Đồng thời xác minh branch `feat/test-quoc-part2` và mọi local changes hiện có. Không checkout/reset/rebase/xóa nhánh cũ. Nếu working tree có thay đổi chưa rõ nguồn gốc, dừng và báo cáo.

Chỉ tạo `feat/k16-quoc-clean-pr` từ `origin/main` khi đã xác minh remote, branch và trạng thái local an toàn. Không sửa trực tiếp `main`.

### 8.2. Commit file kế hoạch trước

Sau khi hoàn thiện và review file kế hoạch:

```powershell
git add KE_HOACH_TRIEN_KHAI_PHAN_VUNG_2.md
git diff --cached --check
git diff --cached
git commit -m "docs(k16): complete partition 2 test plan"
```

Commit này chỉ chứa file kế hoạch, không stage code hoặc test khác.

### 8.3. Mỗi Test Case một commit

Mỗi testcase thực hiện theo vòng đời: `implement → run → review → stage chọn lọc → commit`. Ví dụ commit message:

```text
test(k16): TC-HW-01 create hardware asset
test(k16): TC-HW-02 reject duplicate asset tag
test(k16): TC-HW-03 handle autoincrement collision
test(k16): TC-HW-04 generate asset tag automatically
test(k16): TC-HW-05 verify asset tag gap backfill
test(k16): TC-DP-01 calculate linear depreciation mid-period
test(k16): TC-DP-02 enforce depreciation floor
test(k16): TC-DP-03 calculate depreciation percentage
test(k16): TC-HW-06 require valid asset model
test(k16): TC-HW-07 validate warranty boundaries
test(k16): TC-HW-08 normalize purchase cost
test(k16): TC-HW-09 protect encrypted custom fields
test(k16): TC-HW-10 aggregate component value
test(k16): TC-HW-11 soft delete and restore asset
```

Quy tắc:

- Một commit testcase chỉ chứa test/code cần thiết cho đúng testcase đó và cập nhật tiến độ tương ứng trong file kế hoạch.
- Có thể bao gồm production fix nếu lỗi đã được tái hiện và fix thuộc đúng phạm vi testcase.
- Không gom nhiều testcase độc lập vào một commit.
- Không commit test chưa chạy như thể đã PASS.
- Nếu blocked, cập nhật tài liệu và bằng chứng blocker; không tạo commit testcase mang ý nghĩa hoàn thành. Nếu cần lưu blocker, dùng commit docs riêng có nội dung rõ.
- Không dùng `git add .` hoặc `git add -A`; chỉ `git add <file-path>` đã review.

### 8.4. Kiểm tra sau từng commit

```powershell
git status --short
git show --stat --oneline HEAD
git show --format=fuller --no-ext-diff HEAD --
```

Đối chiếu commit hash vào bảng tiến độ. Không push, tạo PR hoặc merge.

---

## 9. BẢNG THEO DÕI TIẾN ĐỘ VÀ KẾT QUẢ

Bảng dưới đây là baseline khi lập kế hoạch. Chỉ cập nhật `PASS`/`FAIL` sau khi chạy test. Commit hash để trống cho đến khi commit thực tế được tạo.

| Test Case | Ưu tiên theo báo cáo | File test mục tiêu | SQLite/local | Docker/MySQL K16 | Trạng thái tổng | Kết quả / bằng chứng | Commit hash |
|---|---|---|---|---|---|---|---|
| TC-HW-01 | P1 Critical | `tests/Unit/AssetTest.php` | NOT RUN | NOT RUN | PLANNED | Chưa chạy | — |
| TC-HW-02 | P1 Critical | `tests/Unit/AssetTest.php` | NOT RUN | NEEDS VERIFICATION | NEEDS VERIFICATION | Xác minh DB UNIQUE + validation trên MySQL | — |
| TC-HW-03 | P1 Major | `tests/Unit/AssetTest.php` / integration | NOT RUN | NOT RUN | PLANNED | Chưa chạy; cần concurrency thật | — |
| TC-HW-04 | P2 Moderate | `tests/Unit/AssetTest.php` / Feature | NOT RUN | NOT RUN | PLANNED | Chưa chạy | — |
| TC-HW-05 | P3 Minor | `tests/Unit/AssetTest.php` | NOT RUN | NEEDS VERIFICATION | NEEDS VERIFICATION | Xác minh backfill có được hỗ trợ | — |
| TC-DP-01 | P1 Critical | `tests/Unit/DepreciationTest.php` | NOT RUN | NOT RUN | PLANNED | Chưa chạy | — |
| TC-DP-02 | P1 Critical | `tests/Unit/DepreciationTest.php` | NOT RUN | NOT RUN | PLANNED | Chưa chạy | — |
| TC-DP-03 | P1 Major | `tests/Unit/DepreciationTest.php` | NOT RUN | NOT RUN | PLANNED | Chưa chạy | — |
| TC-HW-06 | P1 Major | `tests/Unit/AssetTest.php` / Feature | NOT RUN | NOT RUN | PLANNED | Chưa chạy | — |
| TC-HW-07 | P2 Moderate | `tests/Unit/AssetTest.php` / Feature | NOT RUN | NEEDS VERIFICATION | NEEDS VERIFICATION | Xác minh validation 0–240 | — |
| TC-HW-08 | P2 Moderate | Feature Assets | NOT RUN | NOT RUN | PLANNED | Xác minh parser/locale và MySQL persistence | — |
| TC-HW-09 | P1 Critical | Asset/CustomField Feature test | NOT RUN | NEEDS VERIFICATION | NEEDS VERIFICATION | Xác minh mã hóa + gate trên schema MySQL thật | — |
| TC-HW-10 | P2 Major | `tests/Unit/AssetTest.php` | NOT RUN | NOT RUN | PLANNED | Chưa chạy | — |
| TC-HW-11 | P2 Major | `tests/Unit/AssetTest.php` / Feature | NOT RUN | NOT RUN | PLANNED | Chưa chạy | — |
| AM-01..05 | Bổ sung | `tests/Unit/AssetModelTest.php` | NOT RUN | NEEDS VERIFICATION | NEEDS VERIFICATION | Chốt sau source audit | — |

### 9.1. Bảng tổng kết thực thi theo file

| Bộ test | Môi trường | Tests | Assertions | Passed | Failed | Skipped | Incomplete | Runtime | Evidence / commit |
|---|---|---|---|---|---|---|---|---|---|
| `AssetTest.php` | SQLite/local | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD |
| `AssetTest.php` | Docker/MySQL K16 | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD |
| `DepreciationTest.php` | SQLite/local | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD |
| `DepreciationTest.php` | Docker/MySQL K16 | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD |
| `AssetModelTest.php` | SQLite/local | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD |
| `AssetModelTest.php` | Docker/MySQL K16 | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD |
| Feature tests liên quan | SQLite/local nếu tương thích | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD |
| Feature tests liên quan | Docker/MySQL K16 | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD |
| Dockerized PHP/PHPUnit runner (nếu sử dụng) | Container runner | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD |

---

## 10. DANH MỤC BẰNG CHỨNG NGHIỆM THU

Kết thúc triển khai, tập hợp tối thiểu:

1. Kết quả preflight: repository root, branch, remote, working tree, PHP/PHPUnit, Node/npm và Docker client/server (`docker version`, `docker info`).
2. Diff và schema/migration evidence cho `assets.asset_tag` (index type và unique/non-unique).
3. PHPUnit output của từng testcase và các bộ test theo file.
4. Bằng chứng MySQL Testcontainers: image/tag chính xác (`mysql:8.0.36` nếu runner xác nhận), container lifecycle/readiness, migration, PHPUnit summary, exit code và teardown log.
5. Nếu có chạy chế độ PHP/PHPUnit trong Docker: lệnh `tests/Run-Testcontainers.ps1` thực tế, image ứng dụng, cách mount socket, output và kết quả teardown riêng.
6. Bằng chứng phép tính khấu hao với input/output cụ thể.
7. Bằng chứng Custom Field ciphertext và kết quả truy cập theo quyền.
8. Defect Report + RCA cho các testcase FAIL hoặc sai khác so với báo cáo.
9. Danh sách file thay đổi, `git diff --check`, `git status --short`.
10. Lịch sử commit: commit kế hoạch riêng và commit riêng của từng testcase đã hoàn tất.
11. Báo cáo cuối với số lượng `PASS` / `FAIL` / `BLOCKED` / `NOT RUN`; không thay thế số liệu thật bằng trạng thái kế hoạch.

---

> Tài liệu kế hoạch dành cho **Nguyễn Văn Quốc (2312729)**, Phân vùng 2 — Snipe-IT, học phần Kiểm thử Phần mềm, đề tài R03/K16. Tài liệu được xây dựng theo phạm vi trong báo cáo giữa kỳ; các chi tiết implementation, test method và kết quả phải được xác minh trong repository trước khi nghiệm thu.
