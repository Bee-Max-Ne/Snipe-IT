<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Statuslabel;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('k16')]
class StoreAssetValidationK16Test extends TestCase
{
    /**
     * TC-HW-06: model_id is required when creating an asset.
     */
    public function test_model_id_is_required_when_creating_asset(): void
    {
        $status = Statuslabel::factory()->readyToDeploy()->create();
        $user = User::factory()->createAssets()->create();

        $this->actingAsForApi($user)
            ->postJson(route('api.assets.store'), [
                'model_id' => null,
                'status_id' => $status->id,
                'asset_tag' => 'K16-HW06-001',
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertJsonStructure(['messages' => ['model_id']]);

        $this->assertDatabaseMissing('assets', [
            'asset_tag' => 'K16-HW06-001',
        ]);
    }

    /**
     * TC-HW-07: warranty_months accepts valid boundary and standard values (0, 10, 24, 36, 240).
     */
    #[DataProvider('validWarrantyMonthsProvider')]
    public function test_warranty_months_accepts_valid_values(int $months): void
    {
        $model = AssetModel::factory()->create();
        $status = Statuslabel::factory()->readyToDeploy()->create();
        $user = User::factory()->createAssets()->create();
        $tag = 'K16-HW07-VALID-'.$months;

        $response = $this->actingAsForApi($user)
            ->postJson(route('api.assets.store'), [
                'model_id' => $model->id,
                'status_id' => $status->id,
                'asset_tag' => $tag,
                'warranty_months' => $months,
            ])
            ->assertOk()
            ->assertStatusMessageIs('success')
            ->json();

        $asset = Asset::findOrFail($response['payload']['id']);
        $this->assertEquals($months, $asset->warranty_months);
    }

    public static function validWarrantyMonthsProvider(): array
    {
        return [
            '0 months (lower bound)' => [0],
            '10 months' => [10],
            '24 months' => [24],
            '36 months' => [36],
            '240 months (upper bound)' => [240],
        ];
    }

    /**
     * TC-HW-07: warranty_months rejects out-of-range values (-1, 241).
     */
    #[DataProvider('invalidWarrantyMonthsProvider')]
    public function test_warranty_months_rejects_out_of_range_values(int $months): void
    {
        $model = AssetModel::factory()->create();
        $status = Statuslabel::factory()->readyToDeploy()->create();
        $user = User::factory()->createAssets()->create();
        $tag = 'K16-HW07-INVALID-'.$months;

        $this->actingAsForApi($user)
            ->postJson(route('api.assets.store'), [
                'model_id' => $model->id,
                'status_id' => $status->id,
                'asset_tag' => $tag,
                'warranty_months' => $months,
            ])
            ->assertOk()
            ->assertStatusMessageIs('error')
            ->assertJsonStructure(['messages' => ['warranty_months']]);

        $this->assertDatabaseMissing('assets', [
            'asset_tag' => $tag,
        ]);
    }

    public static function invalidWarrantyMonthsProvider(): array
    {
        return [
            '-1 months (below lower bound)' => [-1],
            '241 months (above upper bound)' => [241],
        ];
    }
}
