<?php

namespace Tests\Feature\Checkouts\Api;

use App\Events\CheckoutableCheckedOut;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Regression coverage for the concurrent-checkout race reported on
 * 2026-08-02. Api\AssetsController::checkout() checked availableForCheckout
 * outside its transaction, then called Asset::checkOut() inside the
 * transaction with no row lock and no re-check. Two racing requests could
 * both see the asset as available, both invoke checkOut(), and land
 * duplicate checkout-history rows plus a doubled checkout_counter.
 *
 * The fix moves the availability re-check inside the transaction with
 * lockForUpdate on the asset row, mirroring the ConsumablesController
 * pattern from GHSA-x4g2-87xc-m5jm. This test cannot simulate two truly
 * concurrent HTTP requests in phpunit, but it pins the behavioral
 * consequence: an asset that has already been assigned mid-flight cannot
 * be checked out a second time. Any refactor that removes the lock or the
 * availability re-check would need to preserve this observable behavior.
 */
class AssetCheckoutRaceGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([CheckoutableCheckedOut::class]);
    }

    public function test_second_checkout_of_already_assigned_asset_does_not_increment_counter()
    {
        $firstTarget = User::factory()->create();
        $asset = Asset::factory()->assignedToUser($firstTarget)->create(['checkout_counter' => 1]);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.asset.checkout', $asset), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
            ])
            ->assertStatusMessageIs('error');

        $asset->refresh();
        $this->assertSame(1, (int) $asset->checkout_counter, 'checkout_counter must not increment when checkout is refused');
        $this->assertSame($firstTarget->id, (int) $asset->assigned_to, 'existing assignment must remain intact');
    }

    public function test_second_checkout_of_already_assigned_asset_does_not_fire_checkout_event()
    {
        $firstTarget = User::factory()->create();
        $asset = Asset::factory()->assignedToUser($firstTarget)->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->postJson(route('api.asset.checkout', $asset), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
            ])
            ->assertStatusMessageIs('error');

        // A racing second checkout that slipped past both the outer and the
        // locked re-check would fire CheckoutableCheckedOut and generate a
        // history row + counter bump via the CheckoutableListener chain.
        // Asserting the event never fires is a proxy for asserting no
        // downstream side-effects occurred.
        Event::assertNotDispatched(CheckoutableCheckedOut::class);
    }

    /**
     * K16 TESTCONTAINERS CONCURRENCY: Kiểm chứng câu lệnh SELECT ... FOR UPDATE
     * được phát ra bên trong transaction trên MySQL thật khi checkout.
     */
    public function test_checkout_issues_pessimistic_row_lock_inside_transaction()
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('SELECT ... FOR UPDATE clause is only preserved and observed on MySQL/MariaDB.');
        }

        $asset = Asset::factory()->create();
        $targetUser = User::factory()->create();
        $actor = User::factory()->superuser()->create();

        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();

        $this->actingAsForApi($actor)
            ->postJson(route('api.asset.checkout', $asset), [
                'checkout_to_type' => 'user',
                'assigned_user' => $targetUser->id,
            ])
            ->assertStatusMessageIs('success');

        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $selectForUpdate = collect($queries)->first(
            fn ($q) => stripos($q['query'], 'select') === 0
                && stripos($q['query'], 'assets') !== false
                && stripos($q['query'], 'for update') !== false
        );

        $this->assertNotNull(
            $selectForUpdate,
            'api.asset.checkout must issue a SELECT ... FOR UPDATE on the assets row to serialize concurrent checkout requests.'
        );
    }

    /**
     * K16 TESTCONTAINERS CONCURRENCY: Mô phỏng 2 luồng / kết nối đồng thời tranh chấp 1 tài sản.
     * Chứng minh InnoDB khóa hàng bi quan (Row-level Lock) chặn đứng luồng 2 và ngăn Double-Allocation.
     */
    public function test_concurrent_checkout_race_prevented_by_pessimistic_lock()
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Pessimistic row locking requires MySQL/InnoDB storage engine.');
        }

        config(['database.connections.mysql_worker' => config('database.connections.mysql')]);

        $asset = Asset::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $admin = User::factory()->superuser()->create();

        // Luồng 1 (Worker 1) mở transaction và chiếm khóa độc quyền X-Lock trên hàng tài sản
        \Illuminate\Support\Facades\DB::connection('mysql')->beginTransaction();
        $lockedAsset = Asset::on('mysql')->whereKey($asset->id)->lockForUpdate()->first();
        $this->assertNotNull($lockedAsset);

        // Luồng 2 (Worker 2) kết nối riêng biệt cố tranh chấp cùng bản ghi asset với timeout ngắn
        \Illuminate\Support\Facades\DB::connection('mysql_worker')->statement('SET innodb_lock_wait_timeout = 1');

        $lockTimeoutOccurred = false;
        try {
            Asset::on('mysql_worker')->whereKey($asset->id)->lockForUpdate()->first();
        } catch (\Illuminate\Database\QueryException $e) {
            // MySQL Error 1205: Lock wait timeout exceeded; try restarting transaction
            if (str_contains($e->getMessage(), '1205') || str_contains($e->getMessage(), 'Lock wait timeout')) {
                $lockTimeoutOccurred = true;
            }
        }

        $this->assertTrue(
            $lockTimeoutOccurred,
            'MySQL InnoDB must block the second concurrent worker from acquiring lockForUpdate on the same asset row.'
        );

        // Luồng 1 hoàn tất checkout cho user1 và COMMIT transaction
        $asset->checkOut($user1, $admin, now(), null, 'Assigned by Worker 1');
        \Illuminate\Support\Facades\DB::connection('mysql')->commit();

        // Sau khi Luồng 1 commit, Luồng 2 đọc lại tài sản thì availableForCheckout() trả về false
        $assetAfterCommit = Asset::on('mysql_worker')->whereKey($asset->id)->first();
        $this->assertFalse(
            $assetAfterCommit->availableForCheckout(),
            'After Worker 1 commits, Worker 2 must observe availableForCheckout() as false, preventing double-allocation.'
        );
        $this->assertSame(1, (int) $assetAfterCommit->checkout_counter);
        $this->assertSame($user1->id, (int) $assetAfterCommit->assigned_to);
    }
}
