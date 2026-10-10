<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_a_pending_user_who_cannot_open_the_drive(): void
    {
        $this->post('/daftar', [
            'name' => 'Budi',
            'email' => 'budi@sdrive.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('account.pending'));

        $this->assertDatabaseHas('users', [
            'email' => 'budi@sdrive.test',
            'role' => 'user',
            'status' => 'pending',
        ]);

        $this->get('/')->assertRedirect(route('account.pending'));
        $this->get('/pengguna')->assertRedirect(route('account.pending'));
    }

    public function test_active_user_can_log_in_and_suspended_user_is_blocked(): void
    {
        $user = User::factory()->create([
            'email' => 'aktif@sdrive.test',
            'password' => 'password123',
        ]);

        $this->post('/login', [
            'email' => 'aktif@sdrive.test',
            'password' => 'password123',
        ])->assertRedirect(route('drive'));

        $this->get('/')->assertOk();

        $user->update(['status' => 'suspended']);
        Auth::forgetGuards();

        $this->get('/')->assertRedirect(route('account.pending'));
    }
}
