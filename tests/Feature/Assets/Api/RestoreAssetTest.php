<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\User;
use Tests\TestCase;

/**
 * WBS Member 2 — Nguyễn Văn Quốc (2312729) — Phân vùng 2
 *
 * TC-HW-11: Xóa mềm và khôi phục tài sản qua API.
 * - Sau xóa: deleted_at != null, tài sản ẩn khỏi danh sách thường.
 * - Sau restore: tài sản xuất hiện trở lại, deleted_at = null.
 * - Không xóa vĩnh viễn dữ liệu hoặc làm mất lịch sử tài sản.
 *
 * Route: DELETE /api/v1/hardware/{id}  → soft-delete
 *        POST   /api/v1/hardware/{id}/restore → restore
 */
class RestoreAssetTest extends TestCase
{
    /**
     * TC-HW-11a: Soft delete qua API — deleted_at được đặt, asset ẩn khỏi index.
     */
    public function test_tc_hw11_soft_delete_sets_deleted_at(): void
    {
        $asset = Asset::factory()->create();
        $actor = User::factory()->deleteAssets()->create();

        $this->actingAsForApi($actor)
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
        $this->assertNotNull(
            Asset::withTrashed()->find($asset->id)->deleted_at,
            'TC-HW-11: deleted_at phải khác null sau soft delete'
        );
    }

    /**
     * TC-HW-11b: Tài sản đã soft-delete không xuất hiện trong index API thông thường.
     */
    public function test_tc_hw11_deleted_asset_hidden_from_normal_api_index(): void
    {
        $asset = Asset::factory()->create(['asset_tag' => 'TC-HW11-HIDDEN-TAG']);
        $actor = User::factory()->deleteAssets()->viewAssets()->create();

        // Xóa
        $this->actingAsForApi($actor)
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertStatusMessageIs('success');

        // Kiểm tra không xuất hiện trong index thông thường
        $response = $this->actingAsForApi($actor)
            ->getJson(route('api.assets.index'))
            ->assertOk();

        $ids = collect($response->json('rows'))->pluck('id')->toArray();
        $this->assertNotContains($asset->id, $ids,
            'TC-HW-11: Tài sản đã soft-delete không được xuất hiện trong index API');
    }

    /**
     * TC-HW-11c: Restore qua API — deleted_at được xóa, asset xuất hiện trở lại.
     * Lưu ý: SnipePermissionsPolicy::delete() kiểm tra empty($item->deleted_at) cho non-admin,
     * nên người dùng có assets.delete nhưng không phải admin sẽ nhận 403 khi restore (DEFECT-HW-11).
     * Dùng admin để xác minh luồng restore API hoạt động đúng.
     */
    public function test_tc_hw11_restore_clears_deleted_at(): void
    {
        $asset = Asset::factory()->create();
        $actor = User::factory()->admin()->create();

        // Soft delete trước
        $this->actingAsForApi($actor)
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);

        // Restore
        $this->actingAsForApi($actor)
            ->postJson(route('api.assets.restore', $asset->id))
            ->assertOk()
            ->assertStatusMessageIs('success');

        $restored = Asset::find($asset->id);
        $this->assertNotNull($restored, 'TC-HW-11: Tài sản phải xuất hiện trở lại sau restore');
        $this->assertNull($restored->deleted_at, 'TC-HW-11: deleted_at phải null sau restore');
    }

    /**
     * TC-HW-11d: Lịch sử tài sản (action log) vẫn tồn tại sau soft delete và restore.
     * Không xóa vĩnh viễn dữ liệu hoặc làm mất lịch sử.
     */
    public function test_tc_hw11_action_log_preserved_after_delete_and_restore(): void
    {
        $asset = Asset::factory()->create();
        $actor = User::factory()->admin()->create();

        // Xóa
        $this->actingAsForApi($actor)
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertStatusMessageIs('success');

        // Restore
        $this->actingAsForApi($actor)
            ->postJson(route('api.assets.restore', $asset->id))
            ->assertOk()
            ->assertStatusMessageIs('success');

        // Action logs: create + delete + restore đều phải có
        $this->assertHasTheseActionLogs($asset, ['create', 'delete', 'restore']);
    }

    /**
     * TC-HW-11e: Người dùng không có quyền delete không được restore tài sản.
     */
    public function test_tc_hw11_user_without_delete_permission_cannot_restore(): void
    {
        $asset = Asset::factory()->deleted()->create();

        // User chỉ có viewAssets, không có deleteAssets
        $actor = User::factory()->viewAssets()->create();

        $this->actingAsForApi($actor)
            ->postJson(route('api.assets.restore', $asset->id))
            ->assertForbidden();

        // Asset vẫn còn soft-deleted
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }
}
