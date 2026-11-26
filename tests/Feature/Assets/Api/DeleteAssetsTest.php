<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Tests\Concerns\TestsFullMultipleCompaniesSupport;
use Tests\Concerns\TestsPermissionsRequirement;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('k16')]
class DeleteAssetsTest extends TestCase implements TestsFullMultipleCompaniesSupport, TestsPermissionsRequirement
{
    public function test_requires_permission()
    {
        $asset = Asset::factory()->create();

        $this->actingAsForApi(User::factory()->create())
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertForbidden();

        $this->assertNotSoftDeleted($asset);
    }

    public function test_adheres_to_full_multiple_companies_support_scoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $assetA = Asset::factory()->for($companyA)->create();
        $assetB = Asset::factory()->for($companyB)->create();
        $assetC = Asset::factory()->for($companyB)->create();

        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $userInCompanyA = $companyA->users()->save(User::factory()->deleteAssets()->make());
        $userInCompanyB = $companyB->users()->save(User::factory()->deleteAssets()->make());

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($userInCompanyA)
            ->deleteJson(route('api.assets.destroy', $assetB))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($userInCompanyB)
            ->deleteJson(route('api.assets.destroy', $assetA))
            ->assertStatusMessageIs('error');

        $this->actingAsForApi($superUser)
            ->deleteJson(route('api.assets.destroy', $assetC))
            ->assertStatusMessageIs('success');

        $this->assertNotSoftDeleted($assetA);
        $this->assertNotSoftDeleted($assetB);
        $this->assertSoftDeleted($assetC);
    }

    public function test_deleting_checked_out_asset_checks_it_in_then_deletes()
    {
        $asset = Asset::factory()->create();
        $user = User::factory()->create();
        $asset->assigned_to = $user->id;
        $asset->assigned_type = User::class;
        $asset->save();

        $this->actingAsForApi(User::factory()->deleteAssets()->create())
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($asset);
        $this->assertNull($asset->fresh()->assigned_to);
        $this->assertHasTheseActionLogs($asset, ['create', 'checkin from', 'delete']);
    }

    public function test_can_delete_asset()
    {
        $asset = Asset::factory()->create();

        $this->actingAsForApi(User::factory()->deleteAssets()->create())
            ->deleteJson(route('api.assets.destroy', $asset))
            ->assertStatusMessageIs('success');

        $this->assertSoftDeleted($asset);
        $this->assertHasTheseActionLogs($asset, ['create', 'delete']);
    }
}
