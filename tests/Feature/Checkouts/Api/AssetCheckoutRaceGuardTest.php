<?php

namespace Tests\Feature\Checkouts\Api;

use App\Events\CheckoutableCheckedOut;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * ==============================================================================
 * BỘ KIỂM THỬ KHÓA BI QUAN & TRANH CHẤP ĐỒNG THỜI (RACE GUARD CONCURRENCY TEST)
 * Phân vùng: Phân vùng 1 — Module API Checkout / Checkin & Cấp phát / Thu hồi
 * Do: 2312718: Phạm Nguyễn Ngọc Phước (Lead QA - Nhóm 05)
 * Kỹ thuật trọng tâm: K16 Testcontainers (Docker MySQL 8.0.36 Ephemeral Container)
 * 
 * Mục tiêu an ninh & toàn vẹn dữ liệu:
 * Kiểm chứng cơ chế khóa hàng bi quan (Pessimistic Row-level Locking: SELECT ... FOR UPDATE)
 * kết hợp giao dịch CSDL (DB::transaction) nhằm ngăn chặn triệt để lỗi cấp phát trùng
 * (Double-Allocation Race Condition) khi có 2 luồng/kết nối đồng thời gọi API cấp phát
 * cho cùng 1 tài sản. SQLite không hỗ trợ tính năng này, bắt buộc phải dùng MySQL thật K16.
 * ==============================================================================
 */
class AssetCheckoutRaceGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([CheckoutableCheckedOut::class]);
    }

    /**
     * Kịch bản: Cấp phát lần hai cho tài sản đã được gán giữa chừng không làm tăng bộ đếm
     * Phân vùng: Phân vùng 1 — API Checkout
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Loại kiểm thử: Regression Testing / Race Condition Prevention
     */
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
        $this->assertSame(1, (int) $asset->checkout_counter, 'Bộ đếm checkout_counter không được phép tăng khi request bị từ chối');
        $this->assertSame($firstTarget->id, (int) $asset->assigned_to, 'Đối tượng gán ban đầu phải được giữ nguyên vẹn');
    }

    /**
     * Kịch bản: Cấp phát lần hai cho tài sản đã được gán không được phát sự kiện CheckoutableCheckedOut
     * Phân vùng: Phân vùng 1 — API Checkout
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Loại kiểm thử: Event Integrity / Side-effect Suppression
     */
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

        // Một request checkout thứ hai nếu lọt qua chốt kiểm tra sẽ kích hoạt sự kiện CheckoutableCheckedOut
        // và tạo ra bản ghi lịch sử cùng tăng counter không mong muốn.
        // Khẳng định sự kiện không phát sinh nhằm chứng minh không có tác dụng phụ nào xảy ra.
        Event::assertNotDispatched(CheckoutableCheckedOut::class);
    }

    /**
     * TC-RC-01B: KIỂM CHỨNG CÂU LỆNH SELECT ... FOR UPDATE PHÁT RA TRONG GIAO DỊCH MYSQL
     * Phân vùng: Phân vùng 1 — Khóa bi quan Concurrency
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Kỹ thuật: SQL Query Audit trên MySQL thật K16 Testcontainers
     * Mục tiêu: Soi vết nhật ký truy vấn để chứng minh mệnh đề khóa độc quyền hàng (X-Lock)
     * được thực thi bên trong transaction khi gọi endpoint cấp phát tài sản.
     */
    public function test_checkout_issues_pessimistic_row_lock_inside_transaction()
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Mệnh đề SELECT ... FOR UPDATE chỉ được hỗ trợ và duy trì trên Storage Engine MySQL/MariaDB.');
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
            'api.asset.checkout bắt buộc phải phát ra câu lệnh SELECT ... FOR UPDATE trên hàng assets để tuần tự hóa các request đồng thời.'
        );
    }

    /**
     * TC-RC-01: MÔ PHỎNG 2 KẾT NỐI SONG SONG ĐỘC LẬP TRANH CHẤP CÙNG 1 TÀI SẢN
     * Phân vùng: Phân vùng 1 — Concurrency Race Guard
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Kỹ thuật: Mô phỏng 2 tiến trình độc lập (Worker 1 và Worker 2) trên MySQL InnoDB K16
     * 
     * Kịch bản mô phỏng:
     * - Luồng 1 (Worker 1) mở transaction và chiếm khóa hàng độc quyền (X-Lock: lockForUpdate).
     * - Luồng 2 (Worker 2) kết nối riêng biệt (mysql_worker) cố gắng tranh chấp cùng 1 bản ghi asset.
     * - Kỳ vọng: MySQL InnoDB chặn đứng Luồng 2, ném ngoại lệ Timeout (MySQL Error 1205).
     *   Sau khi Luồng 1 hoàn tất checkout và commit, Luồng 2 đọc lại trạng thái tài sản nhận được
     *   availableForCheckout() = false, ngăn chặn hoàn toàn lỗi cấp phát trùng (Double-Allocation).
     */
    public function test_concurrent_checkout_race_prevented_by_pessimistic_lock()
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Cơ chế khóa hàng bi quan (Pessimistic Row Lock) bắt buộc chạy trên Storage Engine MySQL InnoDB.');
        }

        config(['database.connections.mysql_worker' => config('database.connections.mysql')]);

        $asset = Asset::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $admin = User::factory()->superuser()->create();

        // Luồng 1 (Worker 1) mở transaction và chiếm khóa độc quyền X-Lock trên hàng tài sản
        \Illuminate\Support\Facades\DB::connection('mysql')->beginTransaction();
        $lockedAsset = Asset::on('mysql')->whereKey($asset->id)->lockForUpdate()->first();
        $this->assertNotNull($lockedAsset, 'Worker 1 phải lấy được bản ghi tài sản để khóa hàng');

        // Luồng 2 (Worker 2) kết nối riêng biệt cố tranh chấp cùng bản ghi asset với thời gian chờ ngắn (1s)
        \Illuminate\Support\Facades\DB::connection('mysql_worker')->statement('SET innodb_lock_wait_timeout = 1');

        $lockTimeoutOccurred = false;
        try {
            Asset::on('mysql_worker')->whereKey($asset->id)->lockForUpdate()->first();
        } catch (\Illuminate\Database\QueryException $e) {
            // Mã lỗi MySQL Error 1205: Lock wait timeout exceeded; try restarting transaction
            if (str_contains($e->getMessage(), '1205') || str_contains($e->getMessage(), 'Lock wait timeout')) {
                $lockTimeoutOccurred = true;
            }
        }

        $this->assertTrue(
            $lockTimeoutOccurred,
            'MySQL InnoDB phải chặn luồng Worker 2 và ném lỗi Lock wait timeout khi cố chiếm khóa trên cùng hàng tài sản đang bị Worker 1 khóa.'
        );

        // Luồng 1 hoàn tất nghiệp vụ checkout cho user1 và COMMIT transaction
        $asset->checkOut($user1, $admin, now(), null, 'Cấp phát bởi Worker 1');
        \Illuminate\Support\Facades\DB::connection('mysql')->commit();

        // Sau khi Luồng 1 commit, Luồng 2 đọc lại tài sản thì availableForCheckout() trả về false
        $assetAfterCommit = Asset::on('mysql_worker')->whereKey($asset->id)->first();
        $this->assertFalse(
            $assetAfterCommit->availableForCheckout(),
            'Sau khi Worker 1 commit, Worker 2 đọc lại tài sản phải thấy availableForCheckout() là false, ngăn ngừa lỗi cấp phát trùng.'
        );
        $this->assertSame(1, (int) $assetAfterCommit->checkout_counter, 'Bộ đếm checkout_counter phải bằng 1');
        $this->assertSame($user1->id, (int) $assetAfterCommit->assigned_to, 'Tài sản phải thuộc về user1 của Worker 1');
    }
}
