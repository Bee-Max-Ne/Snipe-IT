<?php

namespace Tests\Feature\Consumables;

use App\Helpers\Helper;
use App\Models\Consumable;
use App\Models\User;
use Tests\TestCase;

/**
 * TC-CS-02 (Phân vùng 3 – Nguyễn Đình Thạch, 2314506):
 * Cảnh báo tồn kho khi xuất vật tư tiêu hao chạm ngưỡng tối thiểu min_amt.
 *
 * Quy tắc trong Helper::checkLowInventory(): một vật tư bị cảnh báo khi
 *     (qty - số đã xuất) < (min_amt + alert_threshold)
 * tức là NHỎ HƠN HẲN. Với alert_threshold = 0, còn lại đúng bằng min_amt
 * thì chưa cảnh báo; còn lại dưới min_amt thì cảnh báo.
 * Lệnh snipeit:inventory-alerts gửi InventoryAlert khi danh sách khác rỗng.
 *
 * CheckLowInventoryTest (test gốc) đã kiểm tra điểm biên với số lượng đặt sẵn.
 * TC-CS-02 bổ sung luồng đầu-cuối: số lượng giảm do checkout thật qua API rồi
 * mới xét cảnh báo. Lệnh được kiểm tra qua output (như SendInventoryAlertsTest)
 * vì Notification::fake() gọi getKey() mà AlertRecipient không có.
 */
class ConsumableLowStockAlertTest extends TestCase
{
    private function checkout(Consumable $consumable, int $qty): void
    {
        $this->actingAsForApi(User::factory()->checkoutConsumables()->create())
            ->postJson(route('api.consumables.checkout', $consumable), [
                'assigned_to' => User::factory()->create()->id,
                'checkout_qty' => $qty,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');
    }

    private function isFlaggedLowStock(Consumable $consumable): bool
    {
        // checkLowInventory() trả về mảng các phần tử ['id', 'name', 'type', 'remaining', 'min_amt', ...]
        return collect(Helper::checkLowInventory())
            ->contains(fn ($item) => $item['type'] === 'consumables' && (int) $item['id'] === $consumable->id);
    }

    public function test_tc_cs_02_low_stock_alert_fires_only_after_remaining_drops_below_min_amt(): void
    {
        $this->settings->enableAlertEmail('alerts@example.com')->set([
            'alerts_enabled' => 1,
            'alert_threshold' => 0,
        ]);

        $consumable = Consumable::factory()->create(['qty' => 5, 'min_amt' => 3]);

        // Còn 5 > 3: không cảnh báo
        $this->assertFalse($this->isFlaggedLowStock($consumable));

        // Xuất 2, còn 3 = min_amt: chạm ngưỡng nhưng chưa dưới ngưỡng, chưa cảnh báo
        $this->checkout($consumable, 2);
        $this->assertEquals(3, $consumable->fresh()->numRemaining());
        $this->assertFalse($this->isFlaggedLowStock($consumable), 'Còn đúng bằng min_amt chưa được cảnh báo');

        $this->artisan('snipeit:inventory-alerts')
            ->expectsOutputToContain('No low inventory items found')
            ->assertExitCode(0);

        // Xuất thêm 1, còn 2 < min_amt: phải cảnh báo và lệnh báo có hàng dưới mức tối thiểu
        $this->checkout($consumable, 1);
        $this->assertEquals(2, $consumable->fresh()->numRemaining());
        $this->assertTrue($this->isFlaggedLowStock($consumable), 'Còn dưới min_amt phải bị cảnh báo');

        $this->artisan('snipeit:inventory-alerts')
            ->expectsOutputToContain('below minimum inventory')
            ->assertExitCode(0);
    }

    public function test_tc_cs_02_alert_threshold_widens_the_warning_band(): void
    {
        $this->settings->enableAlertEmail('alerts@example.com')->set([
            'alerts_enabled' => 1,
            'alert_threshold' => 2,
        ]);

        $consumable = Consumable::factory()->create(['qty' => 10, 'min_amt' => 3]);

        // Ngưỡng thực tế = min_amt + alert_threshold = 5. Còn 6: chưa cảnh báo
        $this->checkout($consumable, 4);
        $this->assertFalse($this->isFlaggedLowStock($consumable));

        // Còn 4 < 5: cảnh báo sớm dù vẫn còn trên min_amt
        $this->checkout($consumable, 2);
        $this->assertEquals(4, $consumable->fresh()->numRemaining());
        $this->assertTrue($this->isFlaggedLowStock($consumable));
    }

    public function test_tc_cs_02_consumable_without_min_amt_is_never_flagged(): void
    {
        $this->settings->set(['alert_threshold' => 0]);

        $consumable = Consumable::factory()->create(['qty' => 2, 'min_amt' => null]);
        $this->checkout($consumable, 2);

        $this->assertEquals(0, $consumable->fresh()->numRemaining());
        $this->assertFalse($this->isFlaggedLowStock($consumable), 'Không đặt min_amt thì không cảnh báo');
    }
}
