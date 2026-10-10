<?php

namespace Tests\Feature;

use App\Livewire\UserManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_see_or_edit_super_admin_accounts(): void
    {
        $super = User::factory()->superAdmin()->create([
            'email' => 'dev@sdrive.test',
            'name' => 'Developer Tersembunyi',
        ]);
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['email' => 'anggota@sdrive.test']);

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->set('search', 'dev@sdrive.test')
            ->assertDontSee('dev@sdrive.test')
            ->assertDontSee('Developer Tersembunyi')
            ->set('search', 'anggota@sdrive.test')
            ->assertSee('anggota@sdrive.test');

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('edit', $super->id)
            ->assertNotFound();

        $this->actingAs($member)->get('/pengguna')->assertForbidden();
    }

    public function test_super_admin_can_see_super_admin_accounts(): void
    {
        $super = User::factory()->superAdmin()->create(['email' => 'dev@sdrive.test']);

        Livewire::actingAs($super)
            ->test(UserManager::class)
            ->assertSee('dev@sdrive.test');
    }

    public function test_user_form_rejects_super_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->set('name', 'Penyusup')
            ->set('email', 'penyusup@sdrive.test')
            ->set('password', 'password123')
            ->set('role', 'super_admin')
            ->set('status', 'active')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@sdrive.test']);
    }

    public function test_the_user_form_closes_without_saving(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['name' => 'Calon Anggota']);

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('edit', $member->id)
            ->assertSet('modal', true)
            ->set('name', 'Nama yang tidak disimpan')
            ->call('closeModals')
            ->assertSet('modal', false)
            ->assertSee('Calon Anggota')
            ->assertDontSee('Nama yang tidak disimpan');

        $this->assertSame('Calon Anggota', $member->fresh()->name);
    }

    public function test_admin_can_change_a_users_storage_limit(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create([
            'storage_quota' => 15 * 1024 * 1024 * 1024,
        ]);

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('edit', $member->id)
            ->set('quotaGb', '40')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(40 * 1024 * 1024 * 1024, $member->fresh()->storage_quota);
    }

    public function test_storage_limit_must_be_at_least_one_gigabyte(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->set('name', 'Kecil')
            ->set('email', 'kecil@sdrive.test')
            ->set('password', 'password123')
            ->set('role', 'user')
            ->set('status', 'active')
            ->set('quotaGb', '0')
            ->call('save')
            ->assertHasErrors('quotaGb');
    }
}
