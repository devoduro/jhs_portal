<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BuildsSchool;
use Tests\TestCase;

class UsersAndRolesModuleTest extends TestCase
{
    use RefreshDatabase, BuildsSchool;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->admin = $this->staff('Super Admin', ['name' => 'Ann Admin']);
        $this->actingAs($this->admin);
    }

    private function userForm(array $override = []): array
    {
        return $override + ['name' => 'Ben Bursar', 'email' => 'ben@example.test', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'admin', 'system_role' => 'Accountant'];
    }

    public function test_staff_accounts_can_be_created_edited_and_get_a_system_role(): void
    {
        $this->get(route('users.index'))->assertOk()->assertSee('Ann Admin');
        $this->get(route('users.create'))->assertOk();

        $this->post(route('users.store'), $this->userForm(['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        $this->post(route('users.store'), $this->userForm(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post(route('users.store'), $this->userForm(['system_role' => 'Nonexistent']))->assertSessionHasErrors('system_role');
        $this->post(route('users.store'), $this->userForm(['email' => $this->admin->email]))->assertSessionHasErrors('email');

        $this->post(route('users.store'), $this->userForm())->assertRedirect(route('users.index'));
        $ben = User::where('email', 'ben@example.test')->first();
        $this->assertTrue($ben->hasRole('Accountant'));
        $this->assertTrue(Hash::check('secret123', $ben->password));

        $this->get(route('users.show', $ben))->assertOk();
        $this->get(route('users.edit', $ben))->assertOk();
        $this->put(route('users.update', $ben), ['name' => 'Ben B.', 'email' => 'ben@example.test', 'role' => 'admin', 'system_role' => 'Head Teacher'])->assertRedirect(route('users.show', $ben));
        $ben->refresh();
        $this->assertSame('Ben B.', $ben->name);
        $this->assertTrue($ben->hasRole('Head Teacher'));
        $this->assertFalse($ben->hasRole('Accountant'));
        $this->assertTrue(Hash::check('secret123', $ben->password));                  // blank password = unchanged

        $this->get(route('users.index', ['search' => 'Ben B']))->assertViewHas('users', fn ($u) => $u->count() === 1 && $u->first()->name === 'Ben B.');
    }

    public function test_passwords_can_be_reset_by_an_admin(): void
    {
        $ben = $this->staff('Accountant', ['name' => 'Ben']);

        $this->get(route('users.reset-password', $ben))->assertOk();
        $this->put(route('users.update-password', $ben), ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->put(route('users.update-password', $ben), ['password' => 'brandnew99', 'password_confirmation' => 'brandnew99'])->assertRedirect();
        $this->assertTrue(Hash::check('brandnew99', $ben->fresh()->password));
    }

    public function test_only_a_super_admin_can_create_or_change_a_super_admin(): void
    {
        $manager = $this->staff('Head Teacher', ['name' => 'Manager']);
        $manager->givePermissionTo('manage-users');
        $this->actingAs($manager);

        $this->post(route('users.store'), $this->userForm(['system_role' => 'Super Admin']))->assertSessionHasErrors('system_role');
        $this->assertNull(User::where('email', 'ben@example.test')->first());

        // Nor can they promote themselves, or edit the real Super Admin.
        $this->put(route('users.update', $manager), ['name' => 'Manager', 'email' => $manager->email, 'role' => 'admin', 'system_role' => 'Super Admin'])->assertSessionHasErrors('system_role');
        $this->assertFalse($manager->fresh()->hasRole('Super Admin'));
        $this->put(route('users.update', $this->admin), ['name' => 'Hijacked', 'email' => $this->admin->email, 'role' => 'admin', 'system_role' => 'Accountant'])->assertSessionHasErrors('system_role');
        $this->assertSame('Ann Admin', $this->admin->fresh()->name);
        $this->delete(route('users.destroy', $this->admin))->assertSessionHas('error');
        $this->assertNotNull(User::find($this->admin->id));
    }

    public function test_the_last_super_admin_cannot_be_demoted_or_deleted_and_you_cannot_delete_yourself(): void
    {
        $this->put(route('users.update', $this->admin), ['name' => 'Ann Admin', 'email' => $this->admin->email, 'role' => 'admin', 'system_role' => 'Accountant'])->assertSessionHasErrors('system_role');
        $this->assertTrue($this->admin->fresh()->hasRole('Super Admin'));
        $this->delete(route('users.destroy', $this->admin))->assertSessionHas('error');

        // With a second Super Admin in place the first can step down.
        $second = $this->staff('Super Admin', ['name' => 'Second']);
        $this->put(route('users.update', $this->admin), ['name' => 'Ann Admin', 'email' => $this->admin->email, 'role' => 'admin', 'system_role' => 'Accountant'])->assertSessionHasNoErrors();
        $this->assertFalse($this->admin->fresh()->hasRole('Super Admin'));
        $this->assertTrue($second->hasRole('Super Admin'));
    }

    public function test_a_pupil_login_cannot_be_turned_into_a_staff_account(): void
    {
        $pupil = $this->pupilUser($this->pupil('ASANTE Kofi', $this->basic4));

        $this->put(route('users.update', $pupil), ['name' => $pupil->name, 'email' => $pupil->email, 'role' => 'admin'])->assertSessionHasErrors('role');
        $this->assertSame('student', $pupil->fresh()->role);
    }

    public function test_deleting_other_accounts_works(): void
    {
        $ben = $this->staff('Accountant', ['name' => 'Ben']);
        $this->delete(route('users.destroy', $ben))->assertRedirect(route('users.index'));
        $this->assertNull(User::find($ben->id));
    }

    public function test_own_profile_can_be_edited(): void
    {
        $this->get(route('profile'))->assertOk();
        $this->put(route('profile.update'), ['name' => 'Ann A.', 'email' => $this->admin->email, 'phone' => '0244000000'])->assertRedirect(route('profile'));
        $this->assertSame('Ann A.', $this->admin->fresh()->name);

        $this->put(route('profile.update'), ['name' => 'Ann A.', 'email' => $this->admin->email, 'password' => 'newpass123', 'password_confirmation' => 'nope'])->assertSessionHasErrors('password');
        $this->put(route('profile.update'), ['name' => 'Ann A.', 'email' => $this->admin->email, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertRedirect();
        $this->assertTrue(Hash::check('newpass123', $this->admin->fresh()->password));
    }

    // ------------------------------------------------------------------ roles

    public function test_roles_can_be_created_with_permissions_and_custom_roles_edited_and_deleted(): void
    {
        $this->get(route('roles.index'))->assertOk()->assertSee('Teacher');
        $this->get(route('roles.create'))->assertOk();

        $this->post(route('roles.store'), ['name' => 'Teacher', 'permissions' => []])->assertSessionHasErrors('name');                      // taken
        $this->post(route('roles.store'), ['name' => 'Librarian', 'permissions' => ['not-a-permission']])->assertSessionHasErrors('permissions.0');
        $this->post(route('roles.store'), ['name' => 'Librarian', 'permissions' => ['view-student-directory']])->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Librarian')->first();
        $this->assertTrue($role->hasPermissionTo('view-student-directory'));
        $this->get(route('roles.edit', $role))->assertOk();

        $this->put(route('roles.update', $role), ['name' => 'Library Staff', 'permissions' => ['view-student-directory', 'view-reports']])->assertRedirect(route('roles.index'));
        $this->assertSame(2, $role->fresh()->permissions()->count());

        $member = $this->staff('Library Staff');
        $this->delete(route('roles.destroy', $role->fresh()))->assertSessionHas('error');             // still assigned
        $member->removeRole('Library Staff');
        $this->delete(route('roles.destroy', $role->fresh()))->assertSessionHas('success');
    }

    public function test_built_in_roles_keep_their_names_and_super_admin_is_untouchable(): void
    {
        $teacher = Role::where('name', 'Teacher')->first();
        $super = Role::where('name', 'Super Admin')->first();

        $this->put(route('roles.update', $teacher), ['name' => 'Educator', 'permissions' => ['enter-scores']])->assertSessionHasErrors('name');
        $this->assertSame('Teacher', $teacher->fresh()->name);
        $this->put(route('roles.update', $teacher), ['name' => 'Teacher', 'permissions' => ['enter-scores']])->assertRedirect(route('roles.index'));   // permissions can change

        $this->delete(route('roles.destroy', $teacher))->assertSessionHas('error');
        $this->put(route('roles.update', $super), ['name' => 'Boss', 'permissions' => []])->assertSessionHas('error');
        $this->delete(route('roles.destroy', $super))->assertSessionHas('error');
        $this->assertNotNull(Role::where('name', 'Super Admin')->first());
    }

    public function test_permissions_actually_gate_the_screens(): void
    {
        $accountant = $this->staff('Accountant');
        $this->actingAs($accountant);
        $this->get(route('fees.index'))->assertOk();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('settings.index'))->assertForbidden();

        // Granting a permission opens the screen without needing a new login.
        $accountant->givePermissionTo('manage-users');
        $this->get(route('users.index'))->assertOk();
    }
}
