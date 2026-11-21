<?php

namespace Tests\Unit;

use App\Models\Actionlog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_first_name_split()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_firstname = 'Natalia';
        $expected_lastname = "Allanovna Romanova-O'Shostakova";
        $user = User::generateFormattedNameFromFullName($fullname, 'firstname');
        $this->assertEquals($expected_firstname, $user['first_name']);
        $this->assertEquals($expected_lastname, $user['last_name']);
    }

    public function test_first_name()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'natalia';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstname');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_first_name_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'natalia@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstname');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_last_name()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'allanovna-romanova-oshostakova';
        $user = User::generateFormattedNameFromFullName($fullname, 'lastname');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_last_name_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'allanovna-romanova-oshostakova@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'lastname');
        $this->assertEquals($expected_username, $user['username'].'@example.com');
    }

    public function test_first_name_dot_last_name()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'natalia.allanovna-romanova-oshostakova';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstname.lastname');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_first_name_dot_last_name_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'natalia.allanovna-romanova-oshostakova@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstname.lastname');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_last_name_first_initial()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'allanovna-romanova-oshostakovan';
        $user = User::generateFormattedNameFromFullName($fullname, 'lastnamefirstinitial');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_last_name_first_initial_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'allanovna-romanova-oshostakovan@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'lastnamefirstinitial');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_first_initial_last_name()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'nallanovna-romanova-oshostakova';
        $user = User::generateFormattedNameFromFullName($fullname, 'filastname');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_first_initial_last_name_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'nallanovna-romanova-oshostakova@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'filastname');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_first_initial_underscore_last_name()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'nallanovna-romanova-oshostakova';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial_lastname');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_first_initial_underscore_last_name_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'nallanovna-romanova-oshostakova@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial_lastname');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_single_name()
    {
        $fullname = 'Natalia';
        $expected_username = 'natalia';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstname_lastname');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_single_name_email()
    {
        $fullname = 'Natalia';
        $expected_email = 'natalia@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstname_lastname');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_first_initial_dot_lastname()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'nallanovna-romanova-oshostakova';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial.lastname');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_first_initial_dot_lastname_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'nallanovna-romanova-oshostakova@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial.lastname');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_last_name_dot_first_initial()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'allanovna-romanova-oshostakova.n';
        $user = User::generateFormattedNameFromFullName($fullname, 'lastname.firstinitial');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_last_name_dot_first_initial_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'allanovna-romanova-oshostakova.n@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'lastname.firstinitial');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_last_name_underscore_first_initial()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'allanovna-romanova-oshostakova_n';
        $user = User::generateFormattedNameFromFullName($fullname, 'lastname_firstinitial');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_last_name_underscore_first_initial_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'allanovna-romanova-oshostakova_n@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'lastname_firstinitial');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_first_name_last_name()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'nataliaallanovna-romanova-oshostakova';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastname');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_first_name_last_name_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'nataliaallanovna-romanova-oshostakova@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastname');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_first_name_last_initial()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_username = 'nataliaa';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastinitial');
        $this->assertEquals($expected_username, $user['username']);
    }

    public function test_first_name_last_initial_email()
    {
        $fullname = "Natalia Allanovna Romanova-O'Shostakova";
        $expected_email = 'nataliaa@example.com';
        $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastinitial');
        $this->assertEquals($expected_email, $user['username'].'@example.com');
    }

    public function test_link_light_color_attribute()
    {
        // if global branding and user are not set we use the app default defined in the accessor:
        $this->settings->set(['link_light_color' => null]);
        $this->assertEquals(
            '#296282',
            User::factory()->create()->link_light_color
        );

        // nothing set on user we use global branding:
        $this->settings->set(['link_light_color' => '#ff6700']);
        $this->assertEquals(
            '#ff6700',
            User::factory()->create()->link_light_color
        );

        // user has it set we use it:
        $user = User::factory()->create(['link_light_color' => '#61b329']);
        $this->assertEquals(
            '#61b329',
            $user->link_light_color
        );
    }

    public function test_link_dark_color_attribute()
    {
        // if global branding and user are not set we use the app default defined in the accessor:
        $this->settings->set(['link_dark_color' => null]);
        $this->assertEquals(
            '#5fa4cc',
            User::factory()->create()->link_dark_color
        );

        // nothing set on user we use global branding:
        $this->settings->set(['link_dark_color' => '#ff6700']);
        $this->assertEquals(
            '#ff6700',
            User::factory()->create()->link_dark_color
        );

        // user has it set we use it:
        $user = User::factory()->create(['link_dark_color' => '#61b329']);
        $this->assertEquals(
            '#61b329',
            $user->link_dark_color
        );
    }

    public function test_nav_link_color_attribute()
    {
        // if global branding and user are not set we use the app default defined in the accessor:
        $this->settings->set(['nav_link_color' => null]);
        $this->assertEquals(
            '#ffffff',
            User::factory()->create()->nav_link_color
        );

        // nothing set on user we use global branding:
        $this->settings->set(['nav_link_color' => '#ff6700']);
        $this->assertEquals(
            '#ff6700',
            User::factory()->create()->nav_link_color
        );

        // user has it set we use it:
        $user = User::factory()->create(['nav_link_color' => '#61b329']);
        $this->assertEquals(
            '#61b329',
            $user->nav_link_color
        );
    }

    /**
     * WBS Member 4 (Nguyễn Thành Minh - 2312691):
     * Kiểm thử cơ chế phân quyền RBAC (hasAccess()).
     */
    public function test_user_has_access_rbac(): void
    {
        // 1. Superuser luôn có toàn quyền truy cập bất kỳ section nào
        $superUser = User::factory()->superuser()->create();
        $this->assertTrue($superUser->hasAccess('admin'));
        $this->assertTrue($superUser->hasAccess('users.view'));
        $this->assertTrue($superUser->hasAccess('users.delete'));
        $this->assertTrue($superUser->hasAccess('assets.checkout'));

        // 2. Standard user có quyền cụ thể
        $viewerUser = User::factory()->viewUsers()->create();
        $this->assertTrue($viewerUser->hasAccess('users.view'));
        $this->assertFalse($viewerUser->hasAccess('users.delete'));
        $this->assertFalse($viewerUser->hasAccess('admin'));
    }

    /**
     * WBS Member 4 (Nguyễn Thành Minh - 2312691):
     * Kiểm thử chính sách bảo mật UserPolicy ngăn chặn leo thang đặc quyền.
     */
    public function test_user_policy_prevents_privilege_escalation(): void
    {
        $superUser = User::factory()->superuser()->create();
        $regularUser = User::factory()->create();
        $targetUser = User::factory()->create();

        // SuperUser được phép thực hiện mọi thao tác quản trị trên UserPolicy
        $this->assertTrue(Gate::forUser($superUser)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($superUser)->allows('delete', $targetUser));

        // Regular User không có quyền admin/users.delete bị chặn
        $this->assertFalse(Gate::forUser($regularUser)->allows('delete', $targetUser));
    }

    /**
     * WBS Member 4 (Nguyễn Thành Minh - 2312691):
     * Kiểm thử tính bất biến của nhật ký kiểm toán hệ thống (action_logs).
     */
    public function test_action_logs_audit_trail_integrity(): void
    {
        $admin = User::factory()->superuser()->create();
        $target = User::factory()->create();

        $log = new Actionlog;
        $log->item_type = User::class;
        $log->item_id = $target->id;
        $log->action_type = 'create';
        $log->created_by = $admin->id;
        $log->note = 'Initial user audit record';
        $log->save();

        $this->assertDatabaseHas('action_logs', [
            'id' => $log->id,
            'item_type' => User::class,
            'item_id' => $target->id,
            'action_type' => 'create',
            'created_by' => $admin->id,
            'note' => 'Initial user audit record',
        ]);

        // ActionLog duy trì bản ghi vĩnh viễn (không bị xóa mềm / mất dấu)
        $this->assertNull($log->deleted_at);
        $this->assertEquals(1, Actionlog::where('id', $log->id)->count());
    }

    /**
     * Mã kiểm thử: TC-SEC-01
     * Tên kịch bản: Quyền lực tối cao toàn quyền của Superuser (God Mode Bypass)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Root Authority Verification / Positive Baseline
     * Mục tiêu an ninh: Xác minh tài khoản có permissions->superuser = '1' luôn được cấp phép
     * truy cập vào 100% chức năng nhạy cảm mà không bị chặn bởi bất kỳ phân quyền nhóm nào.
     */
    public function test_tc_sec_01_superuser_has_unconditional_access_to_all_sections(): void
    {
        // 1. Arrange: Chuẩn bị tài khoản Superuser và danh sách 10 phân vùng nhạy cảm
        $superUser = User::factory()->superuser()->create();
        $sectionsToTest = [
            'admin',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'assets.view',
            'assets.checkout',
            'assets.checkin',
            'reports.view',
            'settings.general',
        ];

        // 2. Act & Assert: Duyệt qua từng phân vùng để khẳng định quyền tối thượng
        foreach ($sectionsToTest as $section) {
            $this->assertTrue(
                $superUser->hasAccess($section),
                "LỖI AN NINH: Superuser bị chặn truy cập trái phép tại phân vùng: {$section}"
            );
        }

        // Khẳng định cờ isSuperUser hoạt động chính xác
        $this->assertTrue($superUser->isSuperUser(), 'LỖI AN NINH: Cờ isSuperUser không nhận diện được Superuser!');
    }

    /**
     * Mã kiểm thử: TC-SEC-02
     * Tên kịch bản: Giới hạn quyền phân tầng đơn lẻ của người dùng (Least Privilege Boundary)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Role Boundary / Least Privilege Verification
     * Mục tiêu an ninh: Xác minh người dùng chỉ được phép thực thi đúng hành động được cấp phép ('1'),
     * các hành vi chưa cấp hoặc đặt '0' phải bị từ chối tuyệt đối.
     */
    public function test_tc_sec_02_user_with_granular_permissions_is_strictly_bounded(): void
    {
        // 1. Arrange: Tạo User với quyền xem người dùng ('1') và không có quyền tạo ('0')
        $user = User::factory()->create([
            'permissions' => json_encode([
                'users.view' => '1',
                'users.create' => '0',
            ]),
        ]);

        // 2. Act & Assert: Khẳng định ranh giới đặc quyền tối thiểu
        // Quyền được cấp '1' => cho phép
        $this->assertTrue($user->hasAccess('users.view'), 'LỖI: Quyền users.view = 1 nhưng bị từ chối!');

        // Quyền đặt '0' => từ chối
        $this->assertFalse($user->hasAccess('users.create'), 'LỖI AN NINH: Quyền users.create = 0 nhưng vẫn được cấp phép!');

        // Quyền chưa từng gán => mặc định từ chối
        $this->assertFalse($user->hasAccess('users.delete'), 'LỖI AN NINH: Quyền users.delete chưa cấp nhưng được cấp phép!');
        $this->assertFalse($user->hasAccess('admin'), 'LỖI AN NINH: User thường tự có quyền admin!');
    }
}


