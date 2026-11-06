<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Peer\Ecommerce\Database\Seeders\EcommerceSeeder;
use Peer\Ecommerce\Models\Permission;
use Peer\Ecommerce\Models\Role;
use Tests\TestCase;

/**
 * Tests run outside APP_ENV=local, as the live site does: there the panel only opens
 * for users the app's `accessCms` gate lets in.
 */
class CmsPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EcommerceSeeder::class);
    }

    public function test_guests_are_sent_to_the_store_admin_login(): void
    {
        $this->get(route('cms.panel.settings.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_super_admin_can_open_the_panel(): void
    {
        $this->actingAs($this->userWithRole('super-admin'))->get(route('cms.panel.settings.index'))->assertOk();
    }

    public function test_staff_pass_the_gate_for_the_pickers_but_their_role_still_limits_the_screens(): void
    {
        $editor = $this->userWithRole('editor');

        $this->assertTrue(Gate::forUser($editor)->allows('accessCms'));

        // Not a 403: the store's permissions send them back to the dashboard from a screen their role lacks.
        $this->actingAs($editor)->get(route('cms.panel.settings.index'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_shopper_cannot_open_the_panel(): void
    {
        $this->actingAs(User::factory()->create()->fresh())->get(route('cms.panel.settings.index'))->assertForbidden();
    }

    public function test_a_deactivated_admin_cannot_open_the_panel(): void
    {
        $user = $this->userWithRole('super-admin');
        $user->update(['is_active' => false]);

        $this->actingAs($user->fresh())->get(route('cms.panel.settings.index'))->assertForbidden();
    }

    public function test_a_role_with_only_products_passes_the_gate_for_the_pickers(): void
    {
        $this->assertTrue(Gate::forUser($this->userWithPermissions(['products.manage']))->allows('accessCms'));
    }

    public function test_a_role_with_only_store_screens_stays_out_of_the_panel_but_keeps_its_own_screens(): void
    {
        $user = $this->userWithPermissions(['orders.manage']);

        $this->assertFalse(Gate::forUser($user)->allows('accessCms'));
        $this->actingAs($user)->get(route('cms.panel.dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($user)->get(route('admin.orders.index'))->assertOk();
    }

    public function test_a_role_without_permissions_cannot_upload_to_the_media_library(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->postJson(route('cms.media.store'), [])
            ->assertForbidden();
    }

    public function test_the_panel_logs_out_through_the_store_admin_and_keeps_the_cms_defaults(): void
    {
        $this->assertTrue(Route::has(config('cms.auth.logout_route')));
        $this->assertSame('admin.logout', config('cms.auth.logout_route'));
        $this->assertTrue(config('cms.routes.api.enabled'), 'the rest of the CMS config still applies');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'Staff', 'slug' => 'staff', 'is_active' => true]);
        $role->permissions()->sync(Permission::whereIn('slug', $permissions)->pluck('id'));

        $user = User::factory()->create()->fresh();
        $user->roles()->attach($role);

        return $user;
    }

    private function userWithRole(string $role): User
    {
        // fresh() loads is_active's database default, which the admin middleware checks.
        $user = User::factory()->create()->fresh();
        $user->roles()->attach(Role::firstWhere('slug', $role));

        return $user;
    }
}
