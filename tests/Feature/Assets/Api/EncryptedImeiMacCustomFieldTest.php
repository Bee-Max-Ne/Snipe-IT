<?php

namespace Tests\Feature\Assets\Api;

use App\Models\Asset;
use App\Models\CustomField;
use App\Models\Statuslabel;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * WBS Member 2 — Nguyễn Văn Quốc (2312729) — Phân vùng 2
 *
 * TC-HW-09: Mã hóa và bảo vệ Custom Fields nhạy cảm (IMEI & MAC Address).
 * - CSDL không lưu plaintext IMEI/MAC khi field_encrypted = 1.
 * - Người có quyền assets.view.encrypted_custom_fields đọc được dữ liệu giải mã.
 * - Người không có quyền không nhận được plaintext nhạy cảm qua API.
 */
class EncryptedImeiMacCustomFieldTest extends TestCase
{
    public function test_tc_hw09_encrypted_imei_and_mac_are_stored_as_ciphertext_and_protected_by_permission(): void
    {
        $this->markIncompleteIfMySQL('Custom Fields dynamic schema migrations cause implicit commit in MySQL RefreshDatabase');

        $imeiPlaintext = '358764091234567';
        $macPlaintext = '00:1A:2B:3C:4D:5E';

        $imeiField = CustomField::factory()->imei()->encrypt()->create([
            'name' => 'IMEI ' . uniqid(),
        ]);
        $macField = CustomField::factory()->macAddress()->encrypt()->create([
            'name' => 'MAC Address ' . uniqid(),
        ]);

        $status = Statuslabel::factory()->readyToDeploy()->create();
        $superuser = User::factory()->superuser()->create();
        $assetData = Asset::factory()->hasMultipleCustomFields([$imeiField, $macField])->make();

        $response = $this->actingAsForApi($superuser)
            ->postJson(route('api.assets.store'), [
                'asset_tag' => 'TC-HW09-ENC-001',
                'model_id' => $assetData->model->id,
                'status_id' => $status->id,
                $imeiField->db_column_name() => $imeiPlaintext,
                $macField->db_column_name() => $macPlaintext,
            ])
            ->assertStatusMessageIs('success');

        $assetId = $response->json('payload.id');

        // Kiểm tra giá trị vật lý trong CSDL không phải plaintext
        $rawRow = DB::table('assets')->where('id', $assetId)->first();
        $rawImei = $rawRow->{$imeiField->db_column_name()};
        $rawMac = $rawRow->{$macField->db_column_name()};

        $this->assertNotSame($imeiPlaintext, $rawImei, 'TC-HW-09: IMEI trong CSDL không được ở dạng plaintext');
        $this->assertNotSame($macPlaintext, $rawMac, 'TC-HW-09: MAC Address trong CSDL không được ở dạng plaintext');
        $this->assertSame($imeiPlaintext, Crypt::decrypt($rawImei));
        $this->assertSame($macPlaintext, Crypt::decrypt($rawMac));

        // Người có quyền assets.view.encrypted_custom_fields xem được giá trị đã giải mã
        $authorizedUser = User::factory()->viewAssets()->viewEncryptedCustomFields()->create();
        $authorizedResponse = $this->actingAsForApi($authorizedUser)
            ->getJson(route('api.assets.show', $assetId))
            ->assertOk();

        $this->assertSame($imeiPlaintext, $authorizedResponse->json('custom_fields.' . $imeiField->name . '.value'));
        $this->assertSame($macPlaintext, $authorizedResponse->json('custom_fields.' . $macField->name . '.value'));

        // Người chỉ có viewAssets (không có assets.view.encrypted_custom_fields) không được đọc plaintext
        $unauthorizedUser = User::factory()->viewAssets()->create();
        $unauthorizedResponse = $this->actingAsForApi($unauthorizedUser)
            ->getJson(route('api.assets.show', $assetId))
            ->assertOk();

        $this->assertNotSame($imeiPlaintext, $unauthorizedResponse->json('custom_fields.' . $imeiField->name . '.value'));
        $this->assertNotSame($macPlaintext, $unauthorizedResponse->json('custom_fields.' . $macField->name . '.value'));
    }

    public function test_tc_hw09_invalid_mac_address_format_is_rejected(): void
    {
        $this->markIncompleteIfMySQL('Custom Fields dynamic schema migrations cause implicit commit in MySQL RefreshDatabase');

        $macField = CustomField::factory()->macAddress()->encrypt()->create([
            'name' => 'MAC Address ' . uniqid(),
        ]);

        $status = Statuslabel::factory()->readyToDeploy()->create();
        $superuser = User::factory()->superuser()->create();
        $assetData = Asset::factory()->hasMultipleCustomFields([$macField])->make();

        $this->actingAsForApi($superuser)
            ->postJson(route('api.assets.store'), [
                'asset_tag' => 'TC-HW09-BAD-MAC',
                'model_id' => $assetData->model->id,
                'status_id' => $status->id,
                $macField->db_column_name() => 'NOT-A-VALID-MAC',
            ])
            ->assertStatusMessageIs('error');
    }
}
