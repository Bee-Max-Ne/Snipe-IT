<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('k16')]
class RestoreAssetK16Test extends TestCase
{
    /**
     * TC-HW-11: Soft delete a single asset and restore it individually.
     */
    public function test_single_asset_can_be_soft_deleted_and_restored(): void
    {
        $asset = Asset::factory()->create();
        $admin = User::factory()->superuser()->create();

        $this->actingAsForApi($admin)
            ->deleteJson(route('api.assets.destroy', $asset->id))
            ->assertOk()
            ->assertStatusMessageIs('success');

        $deletedAsset = Asset::withTrashed()->findOrFail($asset->id);
        $this->assertNotNull($deletedAsset->deleted_at);
        $this->assertSoftDeleted($asset);

        $this->actingAsForApi($admin)
            ->postJson(route('api.assets.restore', $asset->id))
            ->assertOk()
            ->assertStatusMessageIs('success');

        $restoredAsset = Asset::find($asset->id);
        $this->assertNotNull($restoredAsset);
        $this->assertNull($restoredAsset->deleted_at);
        $this->assertNotSoftDeleted($asset);

        $this->actingAsForApi($admin)
            ->getJson(route('api.assets.show', $asset->id))
            ->assertOk()
            ->assertJsonPath('id', $asset->id);
    }
}
