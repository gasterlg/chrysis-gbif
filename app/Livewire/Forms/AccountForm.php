<?php

namespace App\Livewire\Forms;

use App\Gbif\AccountManager;
use App\Models\Account;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AccountForm extends Form
{
    public ?int $id = null;

    public string $username = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $role = 'SuperUser';

    public string $password = '';

    public string $password_confirmation = '';

    public array $flags = [];

    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:25', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('chrysis.accounts_api', 'username')->ignore($this->id)],
            'first_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:100', Rule::unique('chrysis.accounts_api', 'email')->ignore($this->id)],
            'role' => ['required', Rule::in(['Admin', 'SuperUser'])],
            'password' => [$this->id ? 'nullable' : 'required', 'string', 'min:10', 'max:200', 'confirmed'],
            'flags' => ['array'],
            'flags.*' => ['boolean'],
        ];
    }

    public function fillFrom(Account $a): void
    {
        $this->reset();
        $this->id = $a->id;
        $this->username = (string) $a->username;
        $this->first_name = (string) $a->first_name;
        $this->last_name = (string) $a->last_name;
        $this->email = (string) $a->email;
        $this->role = (string) ($a->role ?? 'SuperUser');
        $this->flags = collect(AccountManager::FLAGS)->mapWithKeys(fn ($f) => [$f => (bool) ($a->{$f} ?? false)])->all();
    }

    public function data(): array
    {
        return [
            'username' => $this->username, 'first_name' => $this->first_name, 'last_name' => $this->last_name,
            'email' => $this->email, 'role' => $this->role,
        ] + collect(AccountManager::FLAGS)->mapWithKeys(fn ($f) => [$f => (bool) ($this->flags[$f] ?? false)])->all();
    }
}
