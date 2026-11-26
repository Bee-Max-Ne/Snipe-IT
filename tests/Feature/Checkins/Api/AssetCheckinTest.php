<?php

namespace Tests\Feature\Checkins\Api;

use App\Events\CheckoutableCheckedIn;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\LicenseSeat;
use App\Models\Location;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * ==============================================================================
 * BỘ KIỂM THỬ API THU HỒI TÀI SẢN (ASSET CHECKIN FEATURE TEST)
 * Phân vùng: Phân vùng 1 — Module API Checkout / Checkin & Cấp phát / Thu hồi
 * Do: 2312718: Phạm Nguyễn Ngọc Phước (Lead QA - Nhóm 05)
 * Kỹ thuật trọng tâm: K16 Testcontainers (Docker MySQL 8.0.36 Ephemeral Container)
 * 
 * Mục tiêu kiểm thử:
 * Toàn diện các kịch bản thu hồi tài sản về kho, tự động giải phóng đối tượng gán,
 * phục hồi vị trí mặc định RTD location, giải phóng ghế bản quyền phần mềm (LicenseSeat),
 * cập nhật vị trí tài sản con (Child Assets), và kiểm chứng tính bất biến chống checkin lặp.
 * ==============================================================================
 */
class AssetCheckinTest extends TestCase
{
    /**
     * Kịch bản: Thu hồi tài sản yêu cầu đúng quyền hạn Policy (checkin)
     * Phân vùng: Phân vùng 1 — Quyền hạn Thu hồi
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Loại kiểm thử: Security & Authorization (403 Forbidden)
     */
    public function test_checking_in_asset_requires_correct_permission()
    {
        $this->actingAsForApi(User::factory()->create())
            ->postJson(route('api.asset.checkin', Asset::factory()->assignedToUser()->create()))
            ->assertForbidden();
    }

    /**
     * Kịch bản: Không thể thu hồi tài sản không tồn tại trong hệ thống
     * Phân vùng: Phân vùng 1 — Validation
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Loại kiểm thử: Negative Testing / Boundary
     */
    public function test_cannot_check_in_non_existent_asset()
    {
        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', ['id' => 'does-not-exist']))
            ->assertStatusMessageIs('error');
    }

    /**
     * Kịch bản: Không thể thu hồi tài sản đang chưa được cấp phát (đang ở trong kho)
     * Phân vùng: Phân vùng 1 — State Validation
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Loại kiểm thử: Negative Testing / State Conflict
     */
    public function test_cannot_check_in_asset_that_is_not_checked_out()
    {
        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', Asset::factory()->create()->id))
            ->assertStatusMessageIs('error');
    }

    /**
     * TC-CI-10: KIỂM CHỨNG TÍNH BẤT BIẾN CHỐNG CHECKIN LẶP LẦN HAI (IDEMPOTENCY GUARD)
     * Phân vùng: Phân vùng 1 — Idempotency Testing
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Mục tiêu: Ngăn chặn việc bấm nút Checkin liên tiếp làm sai lệch bộ đếm checkin_counter
     * hoặc ghi đè thời điểm last_checkin ngoài ý muốn.
     */
    public function test_checkin_cannot_be_repeated_without_mutating_asset(): void
    {
        Event::fake([CheckoutableCheckedIn::class]);

        $asset = Asset::factory()->assignedToUser()->create();
        /** @var User $actor */
        $actor = User::factory()->checkinAssets()->create();

        // 1. Lần thu hồi thứ nhất: thành công hợp lệ
        $this->actingAsForApi($actor)
            ->postJson(route('api.asset.checkin', $asset))
            ->assertOk()
            ->assertStatusMessageIs('success');

        $asset->refresh();
        $checkinCounterAfterFirstRequest = $asset->checkin_counter;
        $lastCheckinAfterFirstRequest = $asset->last_checkin;
        $this->assertNull($asset->assigned_to);
        $this->assertNull($asset->assigned_type);

        // 2. Lần thu hồi thứ hai: hệ thống phải từ chối và giữ nguyên dữ liệu
        $this->postJson(route('api.asset.checkin', $asset))
            ->assertOk()
            ->assertStatusMessageIs('error');

        $asset->refresh();
        $this->assertNull($asset->assigned_to);
        $this->assertNull($asset->assigned_type);
        $this->assertEquals($checkinCounterAfterFirstRequest, $asset->checkin_counter, 'Bộ đếm checkin_counter không được phép tăng lần 2');
        $this->assertEquals($lastCheckinAfterFirstRequest, $asset->last_checkin, 'Thời điểm last_checkin phải giữ nguyên của lần 1');
        Event::assertDispatched(CheckoutableCheckedIn::class, 1);
    }

    /**
     * TC-CI-01: THU HỒI TÀI SẢN THÀNH CÔNG VÀ CẬP NHẬT THÔNG TIN TÀI SẢN
     * Phân vùng: Phân vùng 1 — Functional Positive
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Mục tiêu: Xác nhận tài sản sau thu hồi được xóa đối tượng gán, cập nhật tên mới,
     * trạng thái mới, vị trí kho mới và phát sự kiện CheckoutableCheckedIn.
     */
    public function test_asset_can_be_checked_in()
    {
        Event::fake([CheckoutableCheckedIn::class]);

        $user = User::factory()->create();
        $location = Location::factory()->create();
        $status = Statuslabel::factory()->create();
        $asset = Asset::factory()->assignedToUser($user)->create([
            'expected_checkin' => now()->addDay(),
            'last_checkin' => null,
            'accepted' => 'accepted',
        ]);

        $this->assertTrue($asset->assignedTo->is($user));

        $currentTimestamp = now();

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', $asset), [
                'name' => 'Tên Tài Sản Mới Đổi',
                'status_id' => $status->id,
                'location_id' => $location->id,
            ])
            ->assertOk();

        $this->assertNull($asset->refresh()->assignedTo, 'Quan hệ assignedTo phải bị xóa');
        $this->assertNull($asset->expected_checkin, 'expected_checkin phải trở về null');
        $this->assertNull($asset->assigned_type, 'assigned_type phải trở về null');
        $this->assertNull($asset->accepted, 'Trạng thái accepted phải bị xóa');
        $this->assertEquals('Tên Tài Sản Mới Đổi', $asset->name);
        $this->assertEquals($status->id, $asset->status_id);
        $this->assertTrue($asset->location()->is($location));
        $this->assertHasTheseActionLogs($asset, ['create']);

        Event::assertDispatched(function (CheckoutableCheckedIn $event) use ($currentTimestamp) {
            return (int) Carbon::parse($event->action_date)->diffInSeconds($currentTimestamp, true) < 2;
        }, 1);
    }

    /**
     * TC-CI-02: MẶC ĐỊNH TỰ ĐỘNG PHỤC HỒI TÀI SẢN VỀ VỊ TRÍ KHO BAN ĐẦU (RTD LOCATION)
     * Phân vùng: Phân vùng 1 — Location Restoration
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     */
    public function test_location_is_set_to_rtd_location_by_default_upon_checkin()
    {
        $rtdLocation = Location::factory()->create();
        $asset = Asset::factory()->assignedToUser()->create([
            'location_id' => Location::factory()->create()->id,
            'rtd_location_id' => $rtdLocation->id,
        ]);

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', $asset->id));

        $this->assertTrue($asset->refresh()->location()->is($rtdLocation), 'Vị trí sau thu hồi phải tự động trả về đúng rtd_location_id');
        $this->assertHasTheseActionLogs($asset, ['create', 'checkin from']);
    }

    /**
     * TC-CI-03: CẬP NHẬT KHO MẶC ĐỊNH MỚI KHI THU HỒI TÀI SẢN
     * Phân vùng: Phân vùng 1 — Configuration Update
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     */
    public function test_default_location_can_be_updated_upon_checkin()
    {
        $location = Location::factory()->create();
        $asset = Asset::factory()->assignedToUser()->create();

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', $asset), [
                'location_id' => $location->id,
                'update_default_location' => true,
            ]);

        $this->assertTrue($asset->refresh()->defaultLoc()->is($location), 'Kho mặc định mới phải được cập nhật');
        $this->assertHasTheseActionLogs($asset, ['create', 'checkin from']);
    }

    /**
     * TC-CI-04: TỰ ĐỘNG GIẢI PHÓNG GHẾ BẢN QUYỀN PHẦN MỀM (LICENSE SEATS) KHI THU HỒI
     * Phân vùng: Phân vùng 1 — Data Consistency
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Mục tiêu: Đảm bảo khi thu hồi máy tính, các ghế bản quyền phần mềm đắt tiền được gỡ bỏ
     * để đưa lại vào quỹ bản quyền chung của doanh nghiệp, tránh lãng phí chi phí CNTT.
     */
    public function test_assets_license_seats_are_cleared_upon_checkin()
    {
        $asset = Asset::factory()->assignedToUser()->create();
        LicenseSeat::factory()->assignedToUser()->for($asset)->create();

        $this->assertNotNull($asset->licenseseats->first()->assigned_to, 'Ghế bản quyền ban đầu phải có người dùng');

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', $asset));

        $this->assertNull($asset->refresh()->licenseseats->first()->assigned_to, 'Ghế bản quyền phải được giải phóng assigned_to = null sau khi thu hồi');
    }

    /**
     * TC-CI-05: TỰ ĐỘNG ĐỒNG BỘ CẬP NHẬT VỊ TRÍ CÁC TÀI SẢN CON (CHILD ASSETS CASCADE)
     * Phân vùng: Phân vùng 1 — Relationship Cascade
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     */
    public function test_checking_in_asset_updates_location_of_assets_assigned_to_it()
    {
        $originalLocation = Location::factory()->create();
        $checkedOutLocation = Location::factory()->create();

        $parentAsset = Asset::factory()->assignedToLocation($checkedOutLocation)->create([
            'location_id' => $checkedOutLocation->id,
            'rtd_location_id' => $originalLocation->id,
        ]);

        $childAsset = Asset::factory()->create([
            'assigned_to' => $parentAsset->id,
            'assigned_type' => Asset::class,
            'location_id' => $checkedOutLocation->id,
            'rtd_location_id' => $originalLocation->id,
        ]);

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', $parentAsset), [
                'location_id' => $originalLocation->id,
            ])
            ->assertOk();

        $this->assertEquals($originalLocation->id, $parentAsset->fresh()->location_id);
        $this->assertEquals($originalLocation->id, $childAsset->fresh()->location_id, 'Vị trí của tài sản con phải tự động đồng bộ theo tài sản cha');
        $this->assertEquals($parentAsset->id, $childAsset->fresh()->assigned_to);
        $this->assertEquals(Asset::class, $childAsset->fresh()->assigned_type);
    }

    /**
     * TC-CI-06: CHUẨN HÓA DỮ LIỆU VỊ TRÍ CŨ CÓ ID BẰNG 0 (LEGACY LOCATION NORMALIZATION)
     * Phân vùng: Phân vùng 1 — Legacy Data Compatibility
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     */
    public function test_legacy_location_values_set_to_zero_are_updated()
    {
        $asset = Asset::factory()->canBeInvalidUponCreation()->assignedToUser()->create([
            'rtd_location_id' => 0,
            'location_id' => 0,
        ]);

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', $asset));

        $this->assertNull($asset->refresh()->rtd_location_id, 'Dữ liệu legacy giá trị 0 phải được chuyển đổi thành null');
        $this->assertEquals($asset->location_id, $asset->rtd_location_id);
    }

    /**
     * TC-CI-07: TỰ ĐỘNG DỌN SẠCH CÁC BẢN GHI KÝ NHẬN ĐANG CHỜ (PENDING ACCEPTANCES CLEANUP)
     * Phân vùng: Phân vùng 1 — Acceptance Cleanup
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     */
    public function test_pending_checkout_acceptances_are_cleared_upon_checkin()
    {
        $asset = Asset::factory()->assignedToUser()->create();

        $acceptance = CheckoutAcceptance::factory()->for($asset, 'checkoutable')->pending()->create();

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', $asset));

        $this->assertFalse($acceptance->exists(), 'Bản ghi CheckoutAcceptance pending phải được xóa bỏ khỏi CSDL');
    }

    /**
     * TC-CI-08: THIẾT LẬP THỦ CÔNG THỜI ĐIỂM THU HỒI VÀ GHI CHÚ NHẬT KÝ KIỂM TOÁN
     * Phân vùng: Phân vùng 1 — Audit Data
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     */
    public function test_checkin_time_and_action_log_note_can_be_set()
    {
        Event::fake();

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', Asset::factory()->assignedToUser()->create()), [
                'checkin_at' => '2023-01-02',
                'note' => 'Ghi chú thu hồi máy kiểm toán',
            ]);

        Event::assertDispatched(function (CheckoutableCheckedIn $event) {
            return Carbon::parse('2023-01-02')->isSameDay(Carbon::parse($event->action_date))
                && $event->note === 'Ghi chú thu hồi máy kiểm toán';
        }, 1);
    }

    /**
     * TC-CI-09A: XÓA TÊN TÀI SẢN KHI THU HỒI KHI CÓ CỜ YÊU CẦU CLEAR_NAME = 1
     * Phân vùng: Phân vùng 1 — Boundary Behavior
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     */
    public function test_asset_name_is_cleared_on_checkin_when_clear_name_is_set()
    {
        $asset = Asset::factory()->assignedToUser()->create(['name' => 'Tên Tài Sản Cũ']);

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', $asset), ['clear_name' => '1'])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertNull($asset->refresh()->name, 'Tên tài sản phải bị xóa khi có clear_name = 1');
    }

    /**
     * TC-CI-09B: GIỮ NGUYÊN TÊN TÀI SẢN KHI THU HỒI KHI KHÔNG CÓ CỜ CLEAR_NAME
     * Phân vùng: Phân vùng 1 — Boundary Behavior
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     */
    public function test_asset_name_is_not_cleared_on_checkin_when_clear_name_is_not_set()
    {
        $asset = Asset::factory()->assignedToUser()->create(['name' => 'Tên Tài Sản Giữ Nguyên']);

        $this->actingAsForApi(User::factory()->checkinAssets()->create())
            ->postJson(route('api.asset.checkin', $asset))
            ->assertOk()
            ->assertStatusMessageIs('success');

        $this->assertEquals('Tên Tài Sản Giữ Nguyên', $asset->refresh()->name);
    }

    /**
     * TC-CI-11: THU HỒI TÀI SẢN QUA MÃ THẺ TÀI SẢN TRÊN URL (CHECKIN BY TAG PATH)
     * Phân vùng: Phân vùng 1 — Alternative Identifier Route
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Endpoint: POST hardware/bytag/{any}/checkin (api.asset.checkinbytagPath)
     */
    public function test_asset_can_be_checked_in_by_tag_path(): void
    {
        Event::fake([CheckoutableCheckedIn::class]);

        $asset = Asset::factory()->assignedToUser()->create();
        /** @var User $actor */
        $actor = User::factory()->checkinAssets()->create();

        $response = $this->actingAsForApi($actor)
            ->postJson(route('api.asset.checkinbytagPath', $asset->asset_tag));

        $response->assertOk()
            ->assertStatusMessageIs('success');

        $asset->refresh();
        $this->assertNull($asset->assigned_to);
        $this->assertNull($asset->assigned_type);
        $this->assertNotNull($asset->last_checkin);
        Event::assertDispatched(CheckoutableCheckedIn::class);
    }

    /**
     * TC-CI-11B: THU HỒI TÀI SẢN QUA MÃ THẺ GỬI TRONG BODY (CHECKIN BY TAG BODY)
     * Phân vùng: Phân vùng 1 — Barcode Scanner QuickScan API
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Endpoint: POST hardware/checkinbytag (api.asset.checkinbytag)
     */
    public function test_asset_can_be_checked_in_by_tag_body(): void
    {
        Event::fake([CheckoutableCheckedIn::class]);

        $asset = Asset::factory()->assignedToUser()->create();
        /** @var User $actor */
        $actor = User::factory()->checkinAssets()->create();

        $response = $this->actingAsForApi($actor)
            ->postJson(route('api.asset.checkinbytag'), [
                'asset_tag' => $asset->asset_tag,
            ]);

        $response->assertOk()
            ->assertStatusMessageIs('success');

        $asset->refresh();
        $this->assertNull($asset->assigned_to);
        $this->assertNull($asset->assigned_type);
        $this->assertNotNull($asset->last_checkin);
        Event::assertDispatched(CheckoutableCheckedIn::class);
    }

    /**
     * TC-CI-11C: TỪ CHỐI CHECKIN BY TAG KHI MÃ THẺ KHÔNG TỒN TẠI
     * Phân vùng: Phân vùng 1 — Negative Boundary
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     */
    public function test_checkin_by_tag_fails_when_asset_tag_not_found(): void
    {
        /** @var User $actor */
        $actor = User::factory()->checkinAssets()->create();

        $response = $this->actingAsForApi($actor)
            ->postJson(route('api.asset.checkinbytagPath', 'NON_EXISTENT_TAG_99999'));

        $response->assertOk()
            ->assertStatusMessageIs('error')
            ->assertMessagesAre('Asset with tag NON_EXISTENT_TAG_99999 not found');
    }

    /**
     * TC-CI-12: CHỐT CHẶN BẢO MẬT THU HỒI KHI THIẾU BEARER TOKEN (SECURITY GATE 401)
     * Phân vùng: Phân vùng 1 — Security & Authentication Gate
     * Do: 2312718: Phạm Nguyễn Ngọc Phước
     * Endpoint: POST hardware/{id}/checkin (api.asset.checkin)
     */
    public function test_checkin_fails_without_oauth_passport_token(): void
    {
        $asset = Asset::factory()->assignedToUser()->create();

        // Gửi trực tiếp request checkin không qua actingAsForApi (không có Bearer Token)
        $response = $this->postJson(route('api.asset.checkin', $asset));

        $response->assertUnauthorized();
    }
}
