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
}
