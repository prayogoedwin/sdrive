<?php

namespace App\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.drive')]
class AccountPage extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $notice = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function save(): void
    {
        $user = auth()->user();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ], [
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        if (! empty($data['password'])) {
            $this->validate([
                'current_password' => ['required', 'current_password'],
            ], [
                'current_password.required' => 'Kata sandi saat ini wajib diisi.',
                'current_password.current_password' => 'Kata sandi saat ini salah.',
            ]);
            $user->password = $data['password'];
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->notice = 'Akun diperbarui.';
    }

    public function render()
    {
        return view('livewire.account-page')->layout('layouts.drive', [
            'title' => 'Akun saya — SDrive',
        ]);
    }
}
