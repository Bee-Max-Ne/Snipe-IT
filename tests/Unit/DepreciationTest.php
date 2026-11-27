<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Depreciation;
use App\Models\License;
use Carbon\Carbon;
use Tests\TestCase;

class DepreciationTest extends TestCase
{
    public function test_a_depreciation_has_models()
    {
        $depreciation = Depreciation::factory()->create();

        AssetModel::factory()
            ->count(5)
            ->create(
                [
                    'category_id' => Category::factory()->assetLaptopCategory()->create(),
                    'depreciation_id' => $depreciation->id,
                ]);

        $this->assertEquals(5, $depreciation->models->count());
    }

    public function test_depreciation_amount()
    {
        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'amount',
            'depreciation_min' => 1000,
            'months' => 36,
        ]);

        $asset = Asset::factory()
            ->laptopMbp()
            ->create(
                [
                    'category_id' => Category::factory()->assetLaptopCategory()->create(),
                    'purchase_date' => now()->subDecade(),
                    'purchase_cost' => 4000,
                ]);
        $asset->model->update([
            'depreciation_id' => $depreciation->id,
        ]);

        $asset->getLinearDepreciatedValue();

        $this->assertEquals($depreciation->depreciation_min, $asset->getLinearDepreciatedValue());
    }

    public function test_depreciation_percentage()
    {
        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'percent',
            'depreciation_min' => 50,
            'months' => 36,
        ]);

        $asset = Asset::factory()
            ->laptopMbp()
            ->create(
                [
                    'category_id' => Category::factory()->assetLaptopCategory()->create(),
                    'purchase_date' => now()->subDecade(),
                    'purchase_cost' => 4000,
                ]);
        $asset->model->update([
            'depreciation_id' => $depreciation->id,
        ]);

        $asset->getLinearDepreciatedValue();

        $this->assertEquals(2000, $asset->getLinearDepreciatedValue());
    }

    public function test_a_depreciation_has_licenses()
    {

        $depreciation = Depreciation::factory()->create();
        License::factory()
            ->count(5)
            ->photoshop()
            ->create(
                [
                    'category_id' => Category::factory()->licenseGraphicsCategory()->create(),
                    'depreciation_id' => $depreciation->id,
                ]);

        $this->assertEquals(5, $depreciation->licenses()->count());
    }

    /**
     * WBS Member 2 (Nguyễn Văn Quốc - 2312729):
     * Hai test gốc ở trên chỉ thử tài sản đã khấu hao hết (mua 10 năm trước).
     * Các test dưới kiểm tra công thức đường thẳng GIỮA KỲ:
     * giá trị = giá mua - (giá mua - mức sàn) * (số tháng đã qua / tổng số tháng).
     */
    private function assetWithDepreciation(Depreciation $depreciation, string $purchaseDate, int $cost): Asset
    {
        // Danh mục (category) nằm ở AssetModel do laptopMbp() tạo; bảng assets không có cột category_id
        $asset = Asset::factory()
            ->laptopMbp()
            ->create([
                'purchase_date' => $purchaseDate,
                'purchase_cost' => $cost,
            ]);
        $asset->model->update(['depreciation_id' => $depreciation->id]);

        return $asset->fresh();
    }

    public function test_linear_depreciation_amount_type_mid_period(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'amount',
            'depreciation_min' => 600,
            'months' => 36,
        ]);
        $asset = $this->assetWithDepreciation($depreciation, '2025-06-15', 3600);

        // 12/36 tháng: 3600 - (3600 - 600) * 12/36 = 2600
        $this->assertEquals(2600, $asset->getLinearDepreciatedValue());
    }

    public function test_linear_depreciation_percent_type_mid_period(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'percent',
            'depreciation_min' => 50,
            'months' => 36,
        ]);
        $asset = $this->assetWithDepreciation($depreciation, '2024-12-15', 4000);

        // 18/36 tháng, sàn 50% = 2000: 4000 - (4000 - 2000) * 18/36 = 3000
        $this->assertEquals(3000, $asset->getLinearDepreciatedValue());
    }

    public function test_linear_depreciation_reaches_floor_exactly_at_end_of_period(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'amount',
            'depreciation_min' => 600,
            'months' => 36,
        ]);

        // Còn 1 tháng: 3600 - 3000 * 35/36 = 683,33
        $oneMonthLeft = $this->assetWithDepreciation($depreciation, '2023-07-15', 3600);
        $this->assertEquals(683.33, $oneMonthLeft->getLinearDepreciatedValue());

        // Đúng 36 tháng: chạm mức sàn, không thấp hơn
        $endOfPeriod = $this->assetWithDepreciation($depreciation, '2023-06-15', 3600);
        $this->assertEquals(600, $endOfPeriod->getLinearDepreciatedValue());
    }

    // =========================================================================
    // WBS Member 2 — Nguyễn Văn Quốc (2312729) — Phân vùng 2
    // TS-02.2: Thuật toán khấu hao đường thẳng — TC-DP-01, TC-DP-02, TC-DP-03
    //
    // Công thức (báo cáo):
    //   Khấu hao hàng tháng = (Nguyên giá - Giá trị sàn) / Tổng số tháng
    //   Giá trị còn lại = max(Nguyên giá - (Khấu hao/tháng × Tháng đã qua), Giá trị sàn)
    //
    // Ánh xạ sang implementation thực tế trong Depreciable::getLinearDepreciatedValue():
    //   current_value = round(purchase_cost - (purchase_cost - floor) * (months_passed / total_months), 2)
    // =========================================================================

    /**
     * TC-DP-01: Tính khấu hao giữa chu kỳ — $1200 / 12 tháng / đã dùng 6 tháng.
     * Mong đợi: Giá trị sổ sách còn lại = $600 (50%).
     *
     * Với depreciation_min = 0 (amount type):
     *   current_value = 1200 - (1200 - 0) * (6/12) = 1200 - 600 = 600.
     */
    public function test_tc_dp01_linear_depreciation_mid_period_1200_12m_6m(): void
    {
        // Đặt thời gian hiện tại = ngày mua + 6 tháng
        $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));

        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'amount',
            'depreciation_min'  => 0,
            'months'            => 12,
        ]);

        // Mua ngày 2025-07-01 → tại 2026-01-01 đã qua 6 tháng
        $asset = $this->assetWithDepreciation($depreciation, '2025-07-01', 1200);

        $bookValue = $asset->getLinearDepreciatedValue();

        // Giá trị còn lại = 1200 - 1200 * 6/12 = 600
        $this->assertEquals(600.0, $bookValue,
            'TC-DP-01: Sau 6/12 tháng, giá trị sổ sách phải là $600 (50%)');
    }

    /**
     * TC-DP-02: Không khấu hao dưới giá trị sàn.
     * $1000, đã dùng 36 tháng, depreciation_min (sàn) = $100.
     * Mong đợi: Giá trị sổ sách dừng ở $100, không giảm về 0 hoặc âm.
     *
     * Với 36 tháng đã qua ≥ total months → chuyển sang calculateDepreciation() → trả về sàn.
     * Cần đặt months = 24 để 36 tháng > 24 tháng (đã hết chu kỳ).
     */
    public function test_tc_dp02_book_value_does_not_drop_below_floor(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));

        // Chu kỳ 24 tháng; đặt mua ngày 2023-01-01 → đã qua 36 tháng > 24 tháng
        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'amount',
            'depreciation_min'  => 100,
            'months'            => 24,
        ]);

        $asset = $this->assetWithDepreciation($depreciation, '2023-01-01', 1000);

        $bookValue = $asset->getLinearDepreciatedValue();

        $this->assertNotNull($bookValue, 'TC-DP-02: getLinearDepreciatedValue không được trả về null khi có depreciation + purchase_date');
        $this->assertGreaterThanOrEqual(100, $bookValue,
            'TC-DP-02: Giá trị sổ sách phải >= $100 (giá trị sàn), không được âm hoặc về 0');
        $this->assertEquals(100, $bookValue,
            'TC-DP-02: Sau khi hết chu kỳ, giá trị sổ sách phải dừng đúng ở mức sàn $100');
    }

    /**
     * TC-DP-03: Tính tỷ lệ phần trăm khấu hao.
     * 3 tháng trên chu kỳ 12 tháng → tỷ lệ 25%.
     *
     * Với depreciation_type='percent', depreciation_min=0% (sàn 0% của purchase_cost):
     *   months_passed = 3, total = 12
     *   current_value = purchase_cost - (purchase_cost - 0) * (3/12) = purchase_cost * 0.75
     *   Tỷ lệ đã khấu hao = (purchase_cost - current_value) / purchase_cost * 100 = 25%
     *
     * Implementation: depreciationProgressPercent() trong Asset tính tỷ lệ theo months_passed/total_months.
     */
    public function test_tc_dp03_depreciation_percent_ratio_after_3_months(): void
    {
        $this->travelTo(Carbon::parse('2026-04-01 12:00:00'));

        // depreciation_min = 0 để sàn = 0% của purchase_cost
        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'amount',
            'depreciation_min'  => 0,
            'months'            => 12,
        ]);

        // Mua 2026-01-01 → tại 2026-04-01 đã qua 3 tháng
        $asset = $this->assetWithDepreciation($depreciation, '2026-01-01', 1200);

        // Giá trị còn lại = 1200 - 1200 * (3/12) = 1200 - 300 = 900
        $bookValue = $asset->getLinearDepreciatedValue();
        $this->assertEquals(900.0, $bookValue,
            'TC-DP-03: Sau 3/12 tháng với sàn=0, giá trị còn lại phải là $900');

        // Tỷ lệ đã khấu hao = (1200 - 900) / 1200 * 100 = 25.00%
        $depreciatedAmount = 1200 - $bookValue;
        $percent = round(($depreciatedAmount / 1200) * 100, 2);
        $this->assertEquals(25.00, $percent,
            'TC-DP-03: Tỷ lệ khấu hao sau 3/12 tháng phải là 25.00%');
    }

    /**
     * TC-DP (bổ sung): Giá trị sổ sách không được âm trong mọi trường hợp.
     * Khi không có depreciation_min và đã quá chu kỳ → phải = 0 (không âm).
     */
    public function test_book_value_is_never_negative(): void
    {
        $this->travelTo(Carbon::parse('2030-01-01 12:00:00'));

        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'amount',
            'depreciation_min'  => 0,
            'months'            => 12,
        ]);

        // Mua 2020-01-01 → đã qua 120 tháng >> 12 tháng (hết chu kỳ)
        $asset = $this->assetWithDepreciation($depreciation, '2020-01-01', 500);

        $bookValue = $asset->getLinearDepreciatedValue();

        $this->assertGreaterThanOrEqual(0, $bookValue,
            'Giá trị sổ sách không được âm kể cả khi hết chu kỳ khấu hao');
    }

    /**
     * TC-DP (bổ sung): Chưa khấu hao — tài sản mới mua (0 tháng) phải có giá trị bằng nguyên giá.
     */
    public function test_book_value_equals_purchase_cost_when_not_yet_depreciated(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));

        $depreciation = Depreciation::factory()->create([
            'depreciation_type' => 'amount',
            'depreciation_min'  => 0,
            'months'            => 12,
        ]);

        // Mua đúng hôm nay → 0 tháng đã qua
        $asset = $this->assetWithDepreciation($depreciation, '2026-01-01', 1000);

        $bookValue = $asset->getLinearDepreciatedValue();

        $this->assertEquals(1000.0, $bookValue,
            'Tài sản mới mua (0 tháng) phải có giá trị sổ sách bằng nguyên giá mua');
    }
}
