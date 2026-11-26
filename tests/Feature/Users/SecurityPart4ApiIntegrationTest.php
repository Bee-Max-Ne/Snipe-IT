<?php

namespace Tests\Feature\Users;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Group;
use App\Models\Location;
use App\Models\User;
use Google2FA;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kiểm thử tích hợp HTTP, API Passport Bearer Token, Bảo mật & Đa công ty FMCS cho Phân vùng 4.
 *
 * Bao phủ các kịch bản Feature/Integration đối chiếu với BAO_CAO_GK_Nhom05.docx (Bảng 16–19)
 * và yêu cầu kỹ thuật K16 Testcontainers (>= 5 integration tests trên MySQL 8.0.36 thực tế).
 *
 * Thành viên phụ trách: Nguyễn Thành Minh (MSSV: 2312691)
 */
class SecurityPart4ApiIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Làm sạch bộ nhớ đệm RateLimiter để bảo đảm kiểm thử brute-force lockout có tính tiền định (deterministic)
        Cache::flush();
    }

    /**
     * TC-SEC-API-01: Chặn truy cập API trái phép khi thiếu Passport Bearer Token (401 Unauthorized).
     */
    public function test_tc_sec_api_01_unauthenticated_api_request_is_rejected_with_401(): void
    {
        // Cần ít nhất 1 user trong DB để middleware CheckForSetup không redirect sang /setup
        User::factory()->create();

        // Gọi API danh sách người dùng khi chưa xác thực Bearer Token
        $this->getJson(route('api.users.index'))
            ->assertUnauthorized();

        // Gọi API thông tin cá nhân /api/v1/users/me khi chưa xác thực
        $this->getJson(route('api.users.me'))
            ->assertUnauthorized();
    }

    /**
     * TC-SEC-API-02: Chặn người dùng thường truy cập route tạo tài sản (403) và tự leo thang đặc quyền qua API PUT /users.
     * Đối soát Báo cáo GK: TC-SEC-02 & TC-SEC-06.
     */
    public function test_tc_sec_api_02_regular_user_blocked_from_admin_routes_and_privilege_escalation(): void
    {
        // 1. User chỉ có quyền xem tài sản (assets.view = 1), không có quyền tạo tài sản
        $viewerOnly = User::factory()->viewAssets()->create();

        $this->actingAs($viewerOnly)
            ->get(route('hardware.create'))
            ->assertForbidden();

        // 2. User thường tự gửi PUT /api/v1/users/{self} để gán cờ superuser = 1 -> bị chặn 403 bởi UserPolicy
        $this->actingAsForApi($viewerOnly)
            ->putJson(route('api.users.update', $viewerOnly), [
                'first_name' => $viewerOnly->first_name,
                'last_name' => $viewerOnly->last_name,
                'username' => $viewerOnly->username,
                'email' => $viewerOnly->email,
                'permissions' => json_encode(['superuser' => '1']),
                'activated' => 1,
            ])
            ->assertForbidden();

        $this->assertFalse($viewerOnly->fresh()->isSuperUser(), 'User thường không thể tự nâng quyền Superuser qua API.');

        // 3. User có quyền users.edit (nhưng không phải Superadmin) gửi PUT /api/v1/users/{target} chèn superuser = 1
        $userEditor = User::factory()->editUsers()->create();
        $targetUser = User::factory()->create(['activated' => 1]);

        $this->actingAsForApi($userEditor)
            ->putJson(route('api.users.update', $targetUser), [
                'first_name' => $targetUser->first_name,
                'last_name' => $targetUser->last_name,
                'username' => $targetUser->username,
                'email' => $targetUser->email,
                'permissions' => json_encode(['superuser' => '1', 'assets.view' => '1']),
            ])
            ->assertOk();

        // PreserveUnauthorizedPrivilegedPermissionsAction phải tước bỏ quyền superuser trái phép
        $this->assertFalse($targetUser->fresh()->isSuperUser(), 'Admin cấp dưới không thể phong quyền Superuser cho tài khoản khác.');
    }

    /**
     * TC-SEC-API-03: Ngăn chặn quản trị viên thường xóa tài khoản Superadmin tối cao qua cả Web UI và API.
     * Đối soát Báo cáo GK: TC-SEC-07.
     */
    public function test_tc_sec_api_03_admin_cannot_delete_superadmin_via_web_or_api(): void
    {
        $regularAdmin = User::factory()->admin()->deleteUsers()->create();
        $superadmin = User::factory()->superuser()->create();

        // 1. Thử xóa Superadmin qua Web Controller DELETE /users/{id}
        $this->actingAs($regularAdmin)
            ->delete(route('users.destroy', $superadmin))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($superadmin);

        // 2. Thử xóa Superadmin qua API Controller DELETE /api/v1/users/{id}
        $this->actingAsForApi($regularAdmin)
            ->deleteJson(route('api.users.destroy', $superadmin))
            ->assertOk()
            ->assertStatusMessageIs('error');

        $this->assertNotSoftDeleted($superadmin);
    }

    /**
     * TC-SEC-API-04: Phòng chống tấn công Brute-force bằng cơ chế khóa đăng nhập sau 5 lần nhập sai mật khẩu.
     * Đối soát Báo cáo GK: TC-SEC-08.
     */
    public function test_tc_sec_api_04_login_brute_force_lockout_enforced_after_max_attempts(): void
    {
        $targetUser = User::factory()->create(['username' => 'tc_sec_bruteforce_user']);

        // Cấu hình đúng chuẩn 5 lần sai mật khẩu, khóa 60 giây (1 phút)
        config(['auth.login_throttle.max_attempts' => 5]);
        config(['auth.login_throttle.lockout_duration' => 60]);

        $throttleKey = Str::transliterate(Str::lower('tc_sec_bruteforce_user').'|127.0.0.55');
        RateLimiter::clear($throttleKey);

        // Thực hiện 5 lần đăng nhập sai mật khẩu liên tiếp
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/login')
                ->withServerVariables(['REMOTE_ADDR' => '127.0.0.55'])
                ->post('/login', [
                    'username' => 'tc_sec_bruteforce_user',
                    'password' => 'wrong-password-attempt-'.$attempt,
                ])
                ->assertStatus(302);
        }

        // Xác nhận bảng login_attempts ghi nhận đủ 5 lần thất bại
        $failedCount = DB::table('login_attempts')
            ->where('username', 'tc_sec_bruteforce_user')
            ->where('remote_ip', '127.0.0.55')
            ->where('successful', 0)
            ->count();

        $this->assertSame(5, $failedCount, 'Hệ thống phải lưu đủ 5 lần đăng nhập thất bại vào bảng login_attempts.');

        // Lần thứ 6: Ngay cả khi gửi đúng mật khẩu ('password'), vẫn phải bị chặn bởi Lockout
        $lockedResponse = $this->from('/login')
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.55'])
            ->post('/login', [
                'username' => 'tc_sec_bruteforce_user',
                'password' => 'password',
            ])
            ->assertStatus(302)
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username']);

        $this->followRedirects($lockedResponse)
            ->assertSee(trans('auth.throttle', ['minutes' => 1]));

        $this->assertGuest();
    }

    /**
     * TC-SEC-API-05: Xác thực API Bearer Token với chế độ Đa công ty (FMCS) — cô lập danh sách người dùng và tài sản.
     * Đối soát Báo cáo GK: TC-FMC-01 & TC-FMC-03.
     */
    public function test_tc_sec_api_05_fmcs_api_bearer_token_isolates_users_and_assets_across_companies(): void
    {
        $this->settings->enableMultipleFullCompanySupport();

        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $userCompanyA = User::factory()->viewUsers()->viewAssets()->forCompany($companyA)->create([
            'first_name' => 'NhanSuCongTyA',
        ]);
        $userCompanyB = User::factory()->forCompany($companyB)->create([
            'first_name' => 'NhanSuCongTyB',
        ]);

        $assetCompanyA = Asset::factory()->create([
            'name' => 'Laptop-CongTyA',
            'company_id' => $companyA->id,
        ]);
        $assetCompanyB = Asset::factory()->create([
            'name' => 'Server-CongTyB',
            'company_id' => $companyB->id,
        ]);

        // 1. User Công ty A gọi GET /api/v1/users qua Passport Bearer Token chỉ thấy nhân sự Công ty A
        $usersResponse = $this->actingAsForApi($userCompanyA)
            ->getJson(route('api.users.index'))
            ->assertOk();

        $returnedUserIds = collect($usersResponse->json('rows'))->pluck('id')->all();
        $this->assertContains($userCompanyA->id, $returnedUserIds);
        $this->assertNotContains($userCompanyB->id, $returnedUserIds);

        // 2. User Công ty A gọi GET /api/v1/hardware qua Passport Bearer Token chỉ thấy tài sản Công ty A
        $assetsResponse = $this->actingAsForApi($userCompanyA)
            ->getJson(route('api.assets.index'))
            ->assertOk();

        $returnedAssetIds = collect($assetsResponse->json('rows'))->pluck('id')->all();
        $this->assertContains($assetCompanyA->id, $returnedAssetIds);
        $this->assertNotContains($assetCompanyB->id, $returnedAssetIds);

        // 3. Truy cập trực tiếp tài sản Công ty B qua API bị CompanyableScope ẩn hoàn toàn (chống dò ID chéo công ty)
        $this->actingAsForApi($userCompanyA)
            ->getJson(route('api.assets.show', $assetCompanyB))
            ->assertOk()
            ->assertStatusMessageIs('error');
    }

    /**
     * TC-SEC-API-06: Kiểm thử luồng HTTP Impersonation, nhật ký Actionlog và ghi nhận bằng chứng kết nối MySQL Container K16.
     */
    public function test_tc_sec_api_06_impersonation_feature_flow_and_k16_mysql_container_proof(): void
    {
        $superuser = User::factory()->superuser()->create();
        $target = User::factory()->create(['activated' => 1]);

        // 1. Superuser không nằm trong allowlist cố gọi route mạo danh -> bị chặn 403 Forbidden
        config(['app.user_impersonation_usernames' => ['someone_else_only']]);
        $this->actingAs($superuser)
            ->post(route('users.impersonate.start', $target))
            ->assertForbidden();

        // 2. Superuser hợp lệ trong allowlist thực hiện mạo danh -> chuyển phiên & ghi Actionlog
        config(['app.user_impersonation_usernames' => [$superuser->username]]);
        $this->actingAs($superuser)
            ->post(route('users.impersonate.start', $target), ['note' => 'Kiem tra an ninh Phan vung 4'])
            ->assertRedirect(route('home'));

        $this->assertSame($target->id, auth()->id());
        $this->assertDatabaseHas('action_logs', [
            'item_type' => User::class,
            'item_id' => $target->id,
            'created_by' => $superuser->id,
            'action_type' => 'impersonated',
        ]);

        // 3. Kiểm tra driver DB và xuất bằng chứng nếu đang chạy trên K16 Testcontainers MySQL
        $driver = DB::connection()->getDriverName();
        $this->assertContains($driver, ['mysql', 'sqlite']);
        if (getenv('TESTCONTAINERS_MYSQL') === '1') {
            $this->assertSame('mysql', $driver, 'Khi bật K16 Testcontainers, driver bắt buộc phải là mysql.');
        }

        // Chỉ khẳng định MySQL 8.0.x khi chạy qua runner K16 (TESTCONTAINERS_MYSQL=1).
        // Workflow tests-mysql.yml dùng service mysql:5.7 nên không được ép phiên bản ở đó.
        if ($driver === 'mysql' && getenv('TESTCONTAINERS_MYSQL') === '1') {
            $versionRow = DB::selectOne('SELECT VERSION() AS mysql_version');
            $mysqlVersion = $versionRow->mysql_version ?? '';
            $this->assertStringStartsWith('8.0', $mysqlVersion, 'K16 Testcontainers phải chạy đúng MySQL 8.0.x.');

            $proofDir = storage_path('logs/test-reports');
            if (! is_dir($proofDir)) {
                mkdir($proofDir, 0777, true);
            }

            file_put_contents(
                $proofDir.'/mysql_container_proof.json',
                json_encode([
                    'testcontainers_mysql' => getenv('TESTCONTAINERS_MYSQL') ?: '0',
                    'db_connection' => $driver,
                    'db_host' => config('database.connections.mysql.host'),
                    'db_port' => (string) config('database.connections.mysql.port'),
                    'db_database' => config('database.connections.mysql.database'),
                    'mysql_server_version' => $mysqlVersion,
                    'verified_by_test' => __FUNCTION__,
                    'verified_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
            );
        }
    }

    /**
     * TC-SEC-API-07: Kiểm thử bảo mật xác thực 2 lớp (2FA Enrollment/Verification) và quy trình Logout trong LoginController.
     */
    public function test_tc_sec_api_07_login_controller_two_factor_and_logout_security_enforcement(): void
    {
        $user = User::factory()->create(['activated' => 1]);

        // 1. Chưa đăng nhập truy cập 2FA routes đều phải bị đẩy về /login
        $this->get(route('two-factor-enroll'))->assertRedirect(route('login'));
        $this->get(route('two-factor'))->assertRedirect(route('login'));
        $this->post(route('two-factor'), ['two_factor_secret' => '123456'])->assertRedirect(route('login'));

        // 2. Đăng nhập nhưng chưa cài 2FA -> GET /two-factor đẩy sang /two-factor-enroll
        $this->actingAs($user)
            ->get(route('two-factor'))
            ->assertRedirect(route('two-factor-enroll'));

        // 3. Truy cập /two-factor-enroll sinh mã secret & QR code lưu vào user
        $this->actingAs($user)
            ->get(route('two-factor-enroll'))
            ->assertOk()
            ->assertViewIs('auth.two_factor_enroll');

        $secret = $user->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret);

        // 4. Bỏ trống hoặc nhập sai mã OTP -> không được gắn cờ session 2fa_authed
        $this->actingAs($user)
            ->post(route('two-factor'), ['two_factor_secret' => ''])
            ->assertRedirect(route('two-factor'))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->post(route('two-factor'), ['two_factor_secret' => '000000'])
            ->assertRedirect(route('two-factor'))
            ->assertSessionHas('error');
        $this->assertFalse(session()->has('2fa_authed'));

        // 5. Nhập đúng mã TOTP hợp lệ -> đánh dấu enrolled, ghi 2fa_authed vào session
        $validCode = Google2FA::getCurrentOtp($secret);
        $this->actingAs($user)
            ->post(route('two-factor'), ['two_factor_secret' => $validCode])
            ->assertRedirect(route('home'))
            ->assertSessionHas('2fa_authed', $user->id);

        // Đã enroll rồi mà quay lại trang enroll -> đẩy về two-factor
        $this->actingAs($user->fresh())
            ->get(route('two-factor-enroll'))
            ->assertRedirect(route('two-factor'));

        // 6. GET /logout khi không có phiên SAML SLO bắt buộc phải chặn 405 Method Not Allowed
        $this->actingAs($user->fresh())
            ->get(route('logout.get'))
            ->assertStatus(405);

        // 7. POST /logout hủy phiên, xóa 2fa_authed và chuyển về trạng thái guest
        $this->actingAs($user->fresh())
            ->withSession(['2fa_authed' => $user->id])
            ->post(route('logout.post'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('2fa_authed');

        $this->assertGuest();
    }

    /**
     * TC-SEC-API-08: Kiểm thử phân quyền Api\UsersController trên /me, selectlist (FMCS), history, eulas và ldapsync.
     */
    public function test_tc_sec_api_08_users_api_self_selectlist_history_and_privileged_endpoints(): void
    {
        $this->settings->enableMultipleFullCompanySupport();

        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $viewer = User::factory()->viewAssets()->forCompany($companyA)->create(['show_in_list' => 1]);
        $sameCompanyPeer = User::factory()->forCompany($companyA)->create(['show_in_list' => 1, 'first_name' => 'PeerAlpha']);
        $otherCompanyUser = User::factory()->forCompany($companyB)->create(['show_in_list' => 1, 'first_name' => 'PeerBeta']);
        $adminA = User::factory()->admin()->viewUsers()->forCompany($companyA)->create();

        // 1. /api/v1/users/me trả về đúng hồ sơ chủ token
        $this->actingAsForApi($viewer)
            ->getJson(route('api.users.me'))
            ->assertOk()
            ->assertJsonFragment(['id' => $viewer->id, 'username' => $viewer->username]);

        // 2. /api/v1/users/selectlist cô lập nghiêm ngặt user Công ty B dưới FMCS
        $selectResp = $this->actingAsForApi($adminA)
            ->getJson(route('api.users.selectlist', ['companyId' => $companyA->id, 'excludeId' => $adminA->id]))
            ->assertOk();

        $selectIds = collect($selectResp->json('results'))->pluck('id')->all();
        $this->assertContains($sameCompanyPeer->id, $selectIds);
        $this->assertNotContains($adminA->id, $selectIds);
        $this->assertNotContains($otherCompanyUser->id, $selectIds);

        // 3. User thường bị chặn 403 trên ldapsync, history và eulas của người khác
        $this->actingAsForApi($viewer)->postJson(route('api.users.ldapsync'))->assertForbidden();
        $this->actingAsForApi($adminA)->postJson(route('api.users.ldapsync'))->assertForbidden();
        $this->actingAsForApi($viewer)->getJson(route('api.users.history', $sameCompanyPeer))->assertForbidden();
        $this->actingAsForApi($viewer)->getJson(route('api.user.eulas', $sameCompanyPeer))->assertForbidden();

        // 4. Admin hợp lệ xem được history và eulas của nhân sự cùng công ty
        Actionlog::create([
            'item_type' => User::class,
            'item_id' => $sameCompanyPeer->id,
            'created_by' => $adminA->id,
            'company_id' => $companyA->id,
            'action_type' => 'updated',
            'note' => 'Audit note from TC-SEC-API-08',
        ]);
        $this->actingAsForApi($adminA)->getJson(route('api.users.history', $sameCompanyPeer))->assertOk();
        $this->actingAsForApi($adminA)->getJson(route('api.user.eulas', $sameCompanyPeer))->assertOk();
    }

    /**
     * TC-SEC-API-09: Kiểm thử ma trận CompanyableTrait::canCheckoutTo() & effectiveFmcsCompanyId() cho Location, cha-con và Floater.
     */
    public function test_tc_sec_api_09_companyable_trait_checkout_matrix_for_locations_hierarchy_and_floater(): void
    {
        $parentCompany = Company::factory()->create(['name' => 'Parent Corp']);
        $childCompany = Company::factory()->create(['name' => 'Child Corp', 'parent_id' => $parentCompany->id]);
        $unrelatedCompany = Company::factory()->create(['name' => 'Unrelated Corp']);

        $parentAsset = Asset::factory()->create(['company_id' => $parentCompany->id]);
        $uncompaniedAsset = Asset::factory()->create(['company_id' => null]);

        $childLocation = Location::factory()->create(['company_id' => $childCompany->id]);
        $unrelatedLocation = Location::factory()->create(['company_id' => $unrelatedCompany->id]);
        $uncompaniedLocation = Location::factory()->create(['company_id' => null]);
        $unrelatedUser = User::factory()->forCompany($unrelatedCompany)->create();

        // 1. Khi tắt FMCS: cho phép checkout không giới hạn
        $this->settings->disableMultipleFullCompanySupport();
        $this->assertTrue($parentAsset->canCheckoutTo($unrelatedLocation));
        $this->assertSame($parentCompany->id, $parentAsset->effectiveFmcsCompanyId());
        $this->assertNull($uncompaniedAsset->effectiveFmcsCompanyId());

        // 2. Bật FMCS: kiểm tra nhánh Location khi tắt/bật scope_locations_fmcs và phân cấp cha-con
        $this->settings->enableMultipleFullCompanySupport();
        $this->settings->disableFloaterMode();
        \App\Models\Setting::first()->update(['scope_locations_fmcs' => 0]);
        $this->assertTrue($parentAsset->canCheckoutTo($unrelatedLocation));

        $this->settings->enableScopedLocationsWithFullMultipleCompanySupport();
        $this->assertTrue($parentAsset->canCheckoutTo($childLocation), 'Công ty mẹ phải checkout được tới Location của công ty con.');
        $this->assertFalse($parentAsset->canCheckoutTo($unrelatedLocation), 'Cấm checkout tới Location của công ty khác.');
        $this->assertFalse($parentAsset->canCheckoutTo($uncompaniedLocation), 'Tắt Floater: cấm checkout tài sản công ty tới Location trống.');
        $this->assertFalse($uncompaniedAsset->canCheckoutTo($unrelatedUser), 'Tắt Floater: tài sản trống không checkout cho user đã gắn công ty.');
        $this->assertFalse($uncompaniedAsset->canCheckoutTo($unrelatedLocation));

        // 3. Bật Floater mode
        $this->settings->enableFloaterMode();
        $this->assertTrue($parentAsset->canCheckoutTo($uncompaniedLocation));
        $this->assertTrue($uncompaniedAsset->canCheckoutTo($unrelatedLocation));
    }

    /**
     * TC-SEC-API-10: Kiểm thử các Scope bảo mật/phân cấp của User và Actionlog (Group, SuperAdmins, Manager Hierarchy, Audit Relations).
     */
    public function test_tc_sec_api_10_user_security_scopes_hierarchy_and_actionlog_audit_queries(): void
    {
        $group = Group::factory()->create(['name' => 'Security Auditors']);
        $superuser = User::factory()->superuser()->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['username' => 'mgr_part4']);
        $subordinate = User::factory()->create(['manager_id' => $manager->id, 'username' => 'sub_part4']);
        $subordinate->groups()->attach($group->id);

        // 1. User security query scopes
        $superIds = User::onlySuperAdmins()->pluck('id')->all();
        $adminAndSuperIds = User::onlyAdminsAndSuperAdmins()->pluck('id')->all();
        $groupUserIds = User::byGroup($group->id)->pluck('id')->all();

        $this->assertContains($superuser->id, $superIds);
        $this->assertNotContains($admin->id, $superIds);
        $this->assertContains($admin->id, $adminAndSuperIds);
        $this->assertContains($subordinate->id, $groupUserIds);
        $this->assertNotContains($admin->id, $groupUserIds);

        // 2. Manager hierarchy & exact username matching
        $this->assertTrue($manager->isManagerOf($subordinate));
        $this->assertFalse($subordinate->isManagerOf($manager));
        $this->assertContains($subordinate->id, $manager->getAllSubordinates()->pluck('id')->all());
        $this->assertContains($manager->id, $manager->getAllSubordinatesIncludingSelf()->pluck('id')->all());
        $this->assertSame($manager->id, User::verifyExactUsernameMatch($manager, 'MGR_PART4')?->id);
        $this->assertNull(User::verifyExactUsernameMatch($manager, 'wrong_user'));
        $this->assertNull(User::verifyExactUsernameMatch(null, 'mgr_part4'));

        // 3. Actionlog audit scope & relationships
        $company = Company::factory()->create();
        $location = Location::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create(['company_id' => $company->id, 'name' => 'AuditScopeLaptop']);

        $logEntry = Actionlog::forceCreate([
            'item_type' => Asset::class,
            'item_id' => $asset->id,
            'target_type' => User::class,
            'target_id' => $subordinate->id,
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'location_id' => $location->id,
            'action_type' => 'checkout',
            'action_source' => 'api',
            'note' => 'Security audit trace check',
        ]);

        $fetched = Actionlog::forApiHistory()->where('id', $logEntry->id)->first();
        $this->assertNotNull($fetched);
        $this->assertSame($asset->id, $fetched->item?->id);
        $this->assertContains($company->id, $fetched->company->pluck('id')->all());
        $this->assertSame($subordinate->id, $fetched->target?->id);
        $this->assertSame($location->id, $fetched->location?->id);
        $this->assertSame($admin->id, $fetched->adminuser?->id);

        $searchHits = Actionlog::query()
            ->where(function ($q) use ($logEntry) {
                $logEntry->advancedTextSearch($q, ['sub_part4']);
            })
            ->pluck('id')
            ->all();
        $this->assertContains($logEntry->id, $searchHits);
    }
}
