<?php

namespace Tests\Unit;

use App\Http\Requests\DeleteUserRequest;
use App\Models\Actionlog;


use App\Models\Company;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
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

    /**
     * Mã kiểm thử: TC-SEC-03
     * Tên kịch bản: Giải quyết xung đột - Phủ quyết đích danh thắng cấp phép nhóm (Deny Overrides Allow)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Bảng quyết định xung đột quyền (Explicit Deny Decision Table)
     * Mục tiêu an ninh: Chứng minh nguyên tắc an ninh tối cao: Khi Group cấp phép ('1'),
     * nhưng User bị cấm chỉ định ('-1'), hệ thống BẮT BUỘC phải từ chối (Khóa chặt DEFECT-SEC-03).
     */
    public function test_tc_sec_03_explicit_user_deny_overrides_group_permission_allow(): void
    {
        // 1. Arrange: Tạo Group có quyền xóa và xem; Tạo User bị cấm xóa (-1)
        $group = Group::factory()->create([
            'permissions' => json_encode([
                'users.delete' => '1',
                'users.view' => '1',
                'assets.edit' => '1',
            ]),
        ]);

        $user = User::factory()->create([
            'permissions' => json_encode([
                'users.delete' => '-1', // Cấm tuyệt đối
                'assets.create' => '1',
            ]),
        ]);

        $user->groups()->attach($group->id);

        // 2. Act: Thực thi kiểm tra quyền hạn
        $canDelete = $user->hasAccess('users.delete');
        $canView = $user->hasAccess('users.view');
        $canEditAssets = $user->hasAccess('assets.edit');

        // 3. Assert: Quyền cấm (-1) của user phải ghi đè quyền cho phép (1) của nhóm
        $this->assertFalse(
            $canDelete,
            'LỖI AN NINH NGHIÊM TRỌNG (DEFECT-SEC-03): Quyền cấm chỉ định (-1) bị quyền nhóm (1) ghi đè!'
        );
        $this->assertTrue($canView, 'LỖI: Quyền xem kế thừa từ nhóm hợp lệ nhưng bị từ chối!');
        $this->assertTrue($canEditAssets, 'LỖI: Quyền sửa tài sản kế thừa từ nhóm hợp lệ nhưng bị từ chối!');
    }

    /**
     * Mã kiểm thử: TC-SEC-04
     * Tên kịch bản: Tích lũy kế thừa quyền hạn từ nhiều nhóm vai trò (Multi-Group Aggregation)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Phép hội hợp quyền (Union Permissions Aggregation)
     * Mục tiêu an ninh: Xác minh khi nhân sự tham gia nhiều nhóm, họ kế thừa đầy đủ
     * phép hội (Union) quyền hạn của tất cả các nhóm đó mà không bị xung đột.
     */
    public function test_tc_sec_04_permissions_accumulated_from_multiple_groups(): void
    {
        // 1. Arrange: Tạo 2 nhóm với các quyền chuyên biệt khác nhau
        $groupA = Group::factory()->create([
            'permissions' => json_encode([
                'assets.view' => '1',
                'assets.create' => '1',
            ]),
        ]);

        $groupB = Group::factory()->create([
            'permissions' => json_encode([
                'licenses.view' => '1',
                'licenses.checkout' => '1',
            ]),
        ]);

        // Tạo User không có quyền trực tiếp và gán vào cả 2 nhóm
        $user = User::factory()->create(['permissions' => '']);
        $user->groups()->attach([$groupA->id, $groupB->id]);

        // 2. Act & Assert: Khẳng định user kế thừa hợp quyền của cả 2 nhóm
        $this->assertTrue($user->hasAccess('assets.view'), 'LỖI: Không kế thừa được quyền assets.view từ Nhóm A!');
        $this->assertTrue($user->hasAccess('assets.create'), 'LỖI: Không kế thừa được quyền assets.create từ Nhóm A!');
        $this->assertTrue($user->hasAccess('licenses.view'), 'LỖI: Không kế thừa được quyền licenses.view từ Nhóm B!');
        $this->assertTrue($user->hasAccess('licenses.checkout'), 'LỖI: Không kế thừa được quyền licenses.checkout từ Nhóm B!');

        // Quyền không thuộc nhóm nào => bị từ chối
        $this->assertFalse($user->hasAccess('users.delete'), 'LỖI AN NINH: User tự ý có quyền ngoài các nhóm được gán!');
    }

    /**
     * Mã kiểm thử: TC-SEC-05
     * Tên kịch bản: Cơ chế mặc định từ chối khi không có quyền hạn (Default Deny Baseline)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Phòng thủ chiều sâu (Deny by Default Verification)
     * Mục tiêu an ninh: Chứng minh tài khoản người dùng mới tạo (chưa gán quyền, chưa thuộc nhóm)
     * tuyệt đối bị chặn toàn bộ, không có bất kỳ quyền quản trị hay thao tác dữ liệu nào.
     */
    public function test_tc_sec_05_default_deny_when_no_permissions_assigned(): void
    {
        // 1. Arrange: Tạo User hoàn toàn không có quyền hạn và không có nhóm
        $userEmptyStr = User::factory()->create(['permissions' => '']);
        $userEmptyJson = User::factory()->create(['permissions' => '{}']);

        // 2. Act & Assert: Khẳng định mọi hàm kiểm tra quyền đều trả về false
        foreach ([$userEmptyStr, $userEmptyJson] as $user) {
            $this->assertFalse($user->hasAccess('admin'), 'LỖI AN NINH: User rỗng quyền có quyền admin!');
            $this->assertFalse($user->hasAccess('users.view'), 'LỖI AN NINH: User rỗng quyền có quyền users.view!');
            $this->assertFalse($user->hasAccess('users.create'), 'LỖI AN NINH: User rỗng quyền có quyền users.create!');
            $this->assertFalse($user->hasAccess('assets.checkout'), 'LỖI AN NINH: User rỗng quyền có quyền assets.checkout!');
            $this->assertFalse($user->isSuperUser(), 'LỖI AN NINH: User rỗng quyền là SuperUser!');
        }
    }

    /**
     * Mã kiểm thử: TC-SEC-06
     * Tên kịch bản: Chống leo thang đặc quyền tạo và xóa người dùng qua UserPolicy
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Role Boundary / Least Privilege Verification
     * Mục tiêu an ninh: Ngăn chặn người dùng thường hoặc Viewer lợi dụng quyền để tạo thêm
     * tài khoản quản trị hoặc xóa tài khoản của người khác.
     */
    public function test_tc_sec_06_regular_user_cannot_create_or_delete_users_via_policy(): void
    {
        // 1. Arrange: Chuẩn bị SuperUser, RegularUser (không quyền) và TargetUser
        $superUser = User::factory()->superuser()->create();
        $regularUser = User::factory()->create();
        $viewerUser = User::factory()->viewUsers()->create();
        $targetUser = User::factory()->create();

        // 2. Act & Assert:
        // SuperUser được phép thực hiện mọi thao tác quản trị qua Policy
        $this->assertTrue(Gate::forUser($superUser)->allows('create', User::class), 'LỖI: SuperUser bị chặn tạo người dùng!');
        $this->assertTrue(Gate::forUser($superUser)->allows('delete', $targetUser), 'LỖI: SuperUser bị chặn xóa người dùng!');

        // RegularUser không có quyền admin/users.delete bị chặn hoàn toàn
        $this->assertFalse(Gate::forUser($regularUser)->allows('create', User::class), 'LỖI AN NINH: RegularUser tự ý tạo được người dùng!');
        $this->assertFalse(Gate::forUser($regularUser)->allows('delete', $targetUser), 'LỖI AN NINH: RegularUser tự ý xóa được người dùng khác!');

        // ViewerUser chỉ có quyền xem, bị chặn tạo và xóa người dùng
        $this->assertFalse(Gate::forUser($viewerUser)->allows('create', User::class), 'LỖI AN NINH: ViewerUser tự ý tạo được người dùng!');
        $this->assertFalse(Gate::forUser($viewerUser)->allows('delete', $targetUser), 'LỖI AN NINH: ViewerUser tự ý xóa được người dùng khác!');
    }

    /**
     * Mã kiểm thử: TC-SEC-07
     * Tên kịch bản: Bảo vệ trường dữ liệu xác thực của SuperAdmin (canEditAuthFields)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Anti-Tampering / Privilege Escalation Prevention
     * Mục tiêu an ninh: Đảm bảo Quản trị viên cấp dưới (Sub-admin có quyền users.edit hoặc admin)
     * không thể thay đổi mật khẩu, email hoặc tước quyền của SuperAdmin tối cao.
     */
    public function test_tc_sec_07_subadmin_cannot_modify_superuser_auth_fields(): void
    {
        // 1. Arrange: Chuẩn bị SuperAdmin mục tiêu, Sub-admin quyền users.edit, và Admin thông thường
        $targetSuperUser = User::factory()->superuser()->create();
        $subAdmin = User::factory()->create([
            'permissions' => json_encode(['users.edit' => '1']),
        ]);
        $regularAdmin = User::factory()->create([
            'permissions' => json_encode(['admin' => '1', 'users.edit' => '1']),
        ]);

        // 2. Act & Assert:
        // Sub-admin (chỉ có quyền users.edit) không được phép sửa auth fields của SuperAdmin
        $this->assertFalse(
            Gate::forUser($subAdmin)->allows('canEditAuthFields', $targetSuperUser),
            'LỖI AN NINH: Sub-admin có thể sửa trường xác thực của SuperAdmin!'
        );

        // Admin thông thường (admin = 1) cũng không được phép sửa auth fields của SuperAdmin
        $this->assertFalse(
            Gate::forUser($regularAdmin)->allows('canEditAuthFields', $targetSuperUser),
            'LỖI AN NINH: Regular Admin có thể sửa trường xác thực của SuperAdmin!'
        );

        // SuperAdmin thì có toàn quyền chỉnh sửa trường xác thực của bất kỳ ai (kể cả SubAdmin)
        $this->assertTrue(
            Gate::forUser($targetSuperUser)->allows('canEditAuthFields', $subAdmin),
            'LỖI: SuperAdmin bị chặn quyền sửa trường xác thực của SubAdmin!'
        );
    }

    /**
     * Mã kiểm thử: TC-SEC-08
     * Tên kịch bản: Thiết lập ranh giới an toàn cho tính năng mạo danh (mayImpersonate Guard Clauses)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Security Guard / Adversarial Testing (Ngăn ngừa DEFECT-SEC-04)
     * Mục tiêu an ninh: Ngăn chặn triệt để các hành vi mạo danh nguy hiểm: Admin mạo danh SuperAdmin,
     * mạo danh chính mình, mạo danh tài khoản bị khóa hoặc đã xóa mềm, và người dùng thường cố ý mạo danh.
     */
    public function test_tc_sec_08_impersonation_boundaries_enforced(): void
    {
        // 1. Arrange: Khởi tạo các vai trò người dùng kiểm thử
        $superUser = User::factory()->superuser()->create();
        config(['app.user_impersonation_usernames' => [$superUser->username]]);

        $targetSuperUser = User::factory()->superuser()->create();
        $deactivatedUser = User::factory()->create(['activated' => 0]);
        $trashedUser = User::factory()->create(['activated' => 1]);
        $trashedUser->delete(); // Xóa mềm để có deleted_at khác null

        $activeNormalUser = User::factory()->create(['activated' => 1]);
        $regularUser = User::factory()->create();

        // 2. Act & Assert: Kiểm thử các ranh giới an toàn (Guard Clauses)
        // Ranh giới 1: Chặn tuyệt đối hành vi tự mạo danh chính mình
        $this->assertFalse(
            $superUser->mayImpersonate($superUser),
            'LỖI AN NINH: Cho phép mạo danh chính tài khoản đang đăng nhập!'
        );

        // Ranh giới 2: Chặn SuperUser mạo danh một SuperUser khác (ngăn tẩu tán trách nhiệm kiểm toán)
        $this->assertFalse(
            $superUser->mayImpersonate($targetSuperUser),
            'LỖI AN NINH: Cho phép SuperUser mạo danh một SuperUser khác!'
        );

        // Ranh giới 3: Chặn mạo danh tài khoản đã bị vô hiệu hóa (activated = 0)
        $this->assertFalse(
            $superUser->mayImpersonate($deactivatedUser),
            'LỖI AN NINH: Cho phép mạo danh tài khoản đã bị khóa/vô hiệu hóa!'
        );

        // Ranh giới 4: Chặn mạo danh tài khoản đã bị xóa mềm (trashed / deleted_at != null)
        $this->assertFalse(
            $superUser->mayImpersonate($trashedUser),
            'LỖI AN NINH: Cho phép mạo danh tài khoản đã bị xóa vào thùng rác!'
        );

        // Ranh giới 5: Người dùng thường tuyệt đối không được phép mạo danh bất kỳ ai
        $this->assertFalse(
            $regularUser->mayImpersonate($activeNormalUser),
            'LỖI AN NINH: Người dùng thường không có quyền mà vẫn mạo danh được người khác!'
        );

        // Khẳng định đường hợp lệ (Happy path): SuperUser được phép mạo danh User thường đang kích hoạt
        $this->assertTrue(
            $superUser->mayImpersonate($activeNormalUser),
            'LỖI: SuperUser hợp lệ bị chặn mạo danh tài khoản người dùng thường đang hoạt động!'
        );
    }

    /**
     * Mã kiểm thử: TC-SEC-09
     * Tên kịch bản: Chống tự xóa bản thân ngăn chặn sập quyền quản trị (Self-Deletion Prevention)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Integrity / Negative Testing
     * Mục tiêu an ninh: Ngăn chặn Quản trị viên vô tình hoặc cố ý xóa chính tài khoản mình đang đăng nhập,
     * gây sập phiên làm việc và mất tài khoản quản trị hệ thống.
     */
    public function test_tc_sec_09_admin_cannot_delete_themselves(): void
    {
        // 1. Arrange: Chuẩn bị Admin có toàn quyền xóa người dùng và TargetUser khác
        $admin = User::factory()->superuser()->create();
        $targetUser = User::factory()->create();
        Auth::login($admin);

        // 2. Act & Assert:
        // A. Kiểm thử tầng FormRequest Validation Rule (DeleteUserRequest):
        $request = new DeleteUserRequest();
        $rules = $request->rules();

        // Thử validate yêu cầu xóa chính bản thân admin đang đăng nhập
        $selfDeleteValidator = Validator::make([
            'user' => $admin->id,
            'managed_users' => 0,
            'managed_locations' => 0,
            'assigned_assets' => 0,
            'assigned_licenses' => 0,
            'assigned_accessories' => 0,
            'deleted_at' => null,
        ], $rules, $request->messages());

        $this->assertTrue(
            $selfDeleteValidator->fails(),
            'LỖI AN NINH: Validator cho phép xóa chính tài khoản đang đăng nhập!'
        );
        $this->assertTrue(
            $selfDeleteValidator->errors()->has('user'),
            'LỖI AN NINH: Validator không sinh lỗi trường "user" khi tự xóa bản thân!'
        );

        // Thử validate yêu cầu xóa người dùng mục tiêu khác (hợp lệ)
        $validDeleteValidator = Validator::make([
            'user' => $targetUser->id,
            'managed_users' => 0,
            'managed_locations' => 0,
            'assigned_assets' => 0,
            'assigned_licenses' => 0,
            'assigned_accessories' => 0,
            'deleted_at' => null,
        ], $rules, $request->messages());

        $this->assertFalse(
            $validDeleteValidator->fails(),
            'LỖI: Validator từ chối xóa tài khoản hợp lệ khác!'
        );

        // B. Khẳng định tài khoản Admin vẫn toàn vẹn trong CSDL
        $this->assertNull($admin->fresh()->deleted_at, 'LỖI AN NINH: Tài khoản Admin đã bị gắn cờ xóa mềm!');
        $this->assertModelExists($admin);
    }

    /**
     * Mã kiểm thử: TC-SEC-10
     * Tên kịch bản: Chống leo thang đặc quyền gán quyền Floater toàn tập đoàn (canGrantFloaterStatus)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Multi-tenant Security / Privilege Escalation Prevention (Ngăn ngừa DEFECT-SEC-02)
     * Mục tiêu an ninh: Ngăn chặn Quản trị viên chi nhánh tạo người dùng mới không gắn công ty
     * nhằm biến thành tài khoản "Floater" thấy toàn bộ tập đoàn. Chỉ SuperUser hoặc nhân sự cấp tập đoàn mới có quyền này.
     */
    public function test_tc_sec_10_companied_admin_cannot_grant_floater_status(): void
    {
        // 1. Arrange: Kích hoạt chế độ FMCS Floater Mode và khởi tạo các đối tượng
        $this->settings->enableFloaterMode();

        $company = Company::factory()->create();

        // Quản trị viên chi nhánh được gán trực tiếp vào Company A
        $companyAdmin = User::factory()->create([
            'permissions' => json_encode(['admin' => '1', 'users.create' => '1']),
        ]);
        $companyAdmin->companies()->attach($company);

        // SuperUser toàn quyền hệ thống
        $superUser = User::factory()->superuser()->create();

        // 2. Act & Assert:
        // Quản trị viên thuộc chi nhánh cụ thể tuyệt đối không được phép gán floater status
        $this->assertFalse(
            $companyAdmin->canGrantFloaterStatus(),
            'LỖI AN NINH: Quản trị viên chi nhánh tự ý cấp quyền Floater toàn tập đoàn!'
        );

        // SuperUser tối cao luôn có quyền cấp Floater status
        $this->assertTrue(
            $superUser->canGrantFloaterStatus(),
            'LỖI: SuperUser bị chặn quyền cấp Floater status!'
        );
    }

    /**
     * Mã kiểm thử: TC-SEC-15
     * Tên kịch bản: Tính bất biến và bảo toàn dấu vết nhật ký kiểm toán (Actionlog Non-Repudiation)
     * Phân vùng: Phân vùng 4 (Nguyễn Thành Minh - 2312691)
     * Kỹ thuật: Audit Trail Integrity / Forensic Evidence Preservation
     * Mục tiêu an ninh: Đảm bảo mọi hành vi thay đổi nhân sự và thao tác an ninh đều được ghi vết
     * vĩnh viễn vào bảng action_logs, không bị mất dấu vết kể cả khi đối tượng người dùng bị xóa,
     * đáp ứng tiêu chuẩn kiểm toán an ninh SOC2 / ISO 27001.
     */
    public function test_tc_sec_15_action_log_immutability_and_audit_trail_preservation(): void
    {
        // 1. Arrange: Chuẩn bị Quản trị viên thực hiện hành vi và Người dùng mục tiêu
        $adminActor = User::factory()->superuser()->create();
        $targetUser = User::factory()->create();

        // 2. Act: Ghi nhận sự kiện kiểm toán thay đổi quyền hạn / tạo nhân sự
        $log = new Actionlog();
        $log->item_type = User::class;
        $log->item_id = $targetUser->id;
        $log->created_by = $adminActor->id;
        $log->action_type = 'create';
        $log->note = 'Tạo người dùng và cấp quyền kiểm toán an ninh';
        $log->save();

        // 3. Assert:
        // A. Bản ghi nhật ký kiểm toán tồn tại tức thì trong CSDL
        $this->assertDatabaseHas('action_logs', [
            'id' => $log->id,
            'item_type' => User::class,
            'item_id' => $targetUser->id,
            'created_by' => $adminActor->id,
            'action_type' => 'create',
        ]);

        // B. Bản ghi mới tạo hoàn toàn nguyên vẹn, không bị xóa mềm
        $this->assertNull(
            $log->fresh()->deleted_at,
            'LỖI AN NINH: Bản ghi nhật ký kiểm toán mới tạo đã bị gắn cờ xóa mềm!'
        );

        // C. Khẳng định mối quan hệ kiểm toán (Audit Trail Relation) trỏ chính xác về người thực hiện
        $this->assertEquals(
            $adminActor->id,
            $log->fresh()->adminuser->id,
            'LỖI: Nhật ký kiểm toán không liên kết đúng người thực hiện hành vi!'
        );

        // D. Kịch bản công kích / Xóa bỏ đối tượng mục tiêu:
        // Ngay cả khi người dùng mục tiêu bị xóa, bản ghi Actionlog VẪN PHẢI TỒN TẠI NGUYÊN VẸN trong DB
        $targetUser->delete();

        $this->assertDatabaseHas('action_logs', [
            'id' => $log->id,
            'action_type' => 'create',
        ]);
        $this->assertNotNull(
            Actionlog::find($log->id),
            'LỖI AN NINH: Nhật ký kiểm toán bị biến mất khi đối tượng mục tiêu bị xóa!'
        );
    }
}






