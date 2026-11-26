<?php

namespace Tests\Feature\Licenses\Api;

use App\Models\Actionlog;
use App\Models\License;
use App\Models\User;
use Tests\TestCase;

class UpdateLicenseTest extends TestCase
{
    public function test_update_logs_changed_fields_in_log_meta()
    {
        $license = License::factory()->create(['name' => 'Old Name', 'seats' => 5]);

        $this->actingAsForApi(User::factory()->editLicenses()->create())
            ->patchJson(route('api.licenses.update', $license), [
                'name' => 'New Name',
                'seats' => 10,
                'category_id' => $license->category_id,
            ]);

        $log = Actionlog::where('item_type', License::class)
            ->where('item_id', $license->id)
            ->where('action_type', 'update')
            ->latest()
            ->first();

        $this->assertNotNull($log, 'No update log entry was created');
        $this->assertNotNull($log->log_meta, 'log_meta was not stored');

        $meta = json_decode($log->log_meta, true);
        $this->assertEquals('Old Name', $meta['name']['old']);
        $this->assertEquals('New Name', $meta['name']['new']);
    }

    public function test_no_op_update_does_not_create_log_entry()
    {
        $license = License::factory()->create(['name' => 'Same Name']);

        $before = Actionlog::where('item_type', License::class)
            ->where('item_id', $license->id)
            ->where('action_type', 'update')
            ->count();

        $this->actingAsForApi(User::factory()->editLicenses()->create())
            ->patchJson(route('api.licenses.update', $license), [
                'name' => 'Same Name',
                'category_id' => $license->category_id,
            ]);

        $after = Actionlog::where('item_type', License::class)
            ->where('item_id', $license->id)
            ->where('action_type', 'update')
            ->count();

        $this->assertEquals($before, $after, 'A spurious log entry was created for a no-op update');
    }

    /**
     * TC-LC-04 (Phân vùng 3 – Nguyễn Đình Thạch, 2314506):
     * Chặn cắt giảm tổng số ghế xuống nhỏ hơn số ghế đang cấp phát.
     *
     * License::adjustSeatCount() chỉ được xóa ghế trống (assigned_to và asset_id
     * đều NULL). Nếu số ghế cần xóa lớn hơn số ghế trống, sự kiện updating trả
     * về false nên bản ghi license không được lưu và không ghế nào bị xóa.
     */
    public function test_tc_lc_04_cannot_reduce_seats_below_number_of_assigned_seats(): void
    {
        $license = License::factory()->create(['seats' => 5]);
        $seats = $license->licenseseats()->orderBy('id')->get();
        $holders = User::factory()->count(3)->create();
        foreach ($holders as $i => $holder) {
            $seats[$i]->assigned_to = $holder->id;
            $seats[$i]->save();
        }
        $actor = User::factory()->editLicenses()->create();

        // Giảm từ 5 xuống 2 trong khi 3 ghế đang cấp: phải bị từ chối
        $this->actingAsForApi($actor)
            ->patchJson(route('api.licenses.update', $license), [
                'seats' => 2,
                'category_id' => $license->category_id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('error');

        $license->refresh();
        $this->assertEquals(5, $license->seats, 'Tổng số ghế không được thay đổi');
        $this->assertEquals(5, $license->licenseseats()->count(), 'Không ghế nào được xóa');
        $this->assertEquals(3, $license->licenseseats()->whereNotNull('assigned_to')->count(), 'Các ghế đang cấp phải còn nguyên');
        $this->assertDatabaseMissing('action_logs', [
            'item_type' => License::class,
            'item_id' => $license->id,
            'action_type' => 'delete seats',
        ]);
    }

    /**
     * TC-LC-04 (biên): giảm đúng bằng số ghế đang cấp thì được phép,
     * chỉ ghế trống bị xóa và việc xóa được ghi log.
     */
    public function test_tc_lc_04_reducing_seats_down_to_assigned_count_only_removes_free_seats(): void
    {
        $license = License::factory()->create(['seats' => 5]);
        $seats = $license->licenseseats()->orderBy('id')->get();
        $holders = User::factory()->count(3)->create();
        foreach ($holders as $i => $holder) {
            $seats[$i]->assigned_to = $holder->id;
            $seats[$i]->save();
        }

        $this->actingAsForApi(User::factory()->editLicenses()->create())
            ->patchJson(route('api.licenses.update', $license), [
                'seats' => 3,
                'category_id' => $license->category_id,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success');

        $license->refresh();
        $this->assertEquals(3, $license->seats);
        $this->assertEquals(3, $license->licenseseats()->count());
        $this->assertEquals(3, $license->licenseseats()->whereIn('assigned_to', $holders->pluck('id'))->count(), 'Ghế đang cấp không được bị xóa');
        $this->assertDatabaseHas('action_logs', [
            'item_type' => License::class,
            'item_id' => $license->id,
            'action_type' => 'delete seats',
            'quantity' => 2,
        ]);
    }
}
