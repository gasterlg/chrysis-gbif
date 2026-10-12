<?php

namespace App\Livewire\Admin;

use App\Gbif\AccountManager;
use App\Livewire\Concerns\WithDataTable;
use App\Livewire\Forms\AccountForm;
use App\Models\Account;
use App\Models\AccountPermission;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Users')]
class Users extends Component
{
    use WithDataTable;

    public AccountForm $form;

    public ?int $passwordFor = null;

    #[Validate('required|string|min:10|max:200|confirmed')]
    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public function mount(): void
    {
        $this->authorize('admin');
        if ($this->sortField === '') {
            $this->sortField = 'username';
        }
    }

    protected function baseQuery(): Builder
    {
        return Account::query()->select(['id', 'username', 'first_name', 'last_name', 'email', 'role'])->toBase();
    }

    /** @return array<int, array{update_gbif: bool, disabled: bool}> */
    private function permissions(): array
    {
        return AccountPermission::query()->get()->keyBy('account_id')->map(fn ($p) => ['update_gbif' => (bool) $p->update_gbif, 'disabled' => (bool) $p->disabled])->all();
    }

    protected function columns(): array
    {
        $perm = $this->permissions();
        $me = auth()->id();
        $badge = fn (string $t, string $c) => view('components.badge', ['color' => $c, 'slot' => $t, 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();

        return [
            Column::make('username', 'Username')->sortable()->searchable()
                ->format(fn ($r) => e($r->username).($r->id === $me ? ' <span class="text-xs text-slate-400">(you)</span>' : ''), html: true),
            Column::make('name', 'Name')->sortable('last_name,first_name')->searchable('first_name,last_name')->format(fn ($r) => trim($r->first_name.' '.$r->last_name)),
            Column::make('email', 'E-mail')->sortable()->searchable(),
            Column::make('role', 'Role')->sortable()->format(fn ($r) => $badge($r->role ?? '—', $r->role === 'Admin' ? 'blue' : 'slate'), html: true),
            Column::make('update_gbif', 'update_gbif')
                ->format(fn ($r) => '<button type="button" wire:click="toggleUpdateGbif('.$r->id.')" class="text-xs" data-toggle-gbif="'.$r->id.'">'
                    .(($perm[$r->id]['update_gbif'] ?? false) ? $badge('yes', 'green') : $badge('no', 'slate')).'</button>', html: true),
            Column::make('status', 'Status')->format(fn ($r) => ($perm[$r->id]['disabled'] ?? false) ? $badge('disabled', 'red') : $badge('active', 'green'), html: true),
            Column::make('actions', '')->noExport()->class('whitespace-nowrap text-right')
                ->format(fn ($r) => view('livewire.admin.user-actions', ['r' => $r, 'disabled' => $perm[$r->id]['disabled'] ?? false, 'me' => $me])->render(), html: true),
        ];
    }

    protected function filters(): array
    {
        return [Filter::select('role', 'Role', ['Admin' => 'Admin', 'SuperUser' => 'SuperUser'])];
    }

    private function manager(): AccountManager
    {
        return app(AccountManager::class);
    }

    public function create(): void
    {
        $this->authorize('admin');
        $this->form->reset();
        $this->form->flags = array_fill_keys(AccountManager::FLAGS, false);
        $this->resetErrorBag();
        $this->dispatch('open-modal', name: 'user-form');
    }

    public function edit(int $id): void
    {
        $this->authorize('admin');
        $this->form->fillFrom(Account::findOrFail($id));
        $this->resetErrorBag();
        $this->dispatch('open-modal', name: 'user-form');
    }

    public function save(): void
    {
        $this->authorize('admin');
        $this->form->validate();
        try {
            if ($this->form->id) {
                $this->manager()->update(Account::findOrFail($this->form->id), $this->form->data(), auth()->user());
                if ($this->form->password !== '') {
                    $this->manager()->resetPassword(Account::findOrFail($this->form->id), $this->form->password, auth()->user());
                }
                $msg = "User {$this->form->username} saved.";
            } else {
                $this->manager()->create($this->form->data(), $this->form->password, auth()->user());
                $msg = "User {$this->form->username} created.";
            }
        } catch (InvalidArgumentException $e) {
            $this->addError('form.role', $e->getMessage());

            return;
        }
        $this->dispatch('close-modal', name: 'user-form');
        $this->dispatch('toast', type: 'success', message: $msg);
        $this->form->reset();
    }

    public function askPassword(int $id): void
    {
        $this->authorize('admin');
        $this->passwordFor = $id;
        $this->reset('newPassword', 'newPassword_confirmation');
        $this->resetErrorBag();
        $this->dispatch('open-modal', name: 'user-password');
    }

    public function resetPassword(): void
    {
        $this->authorize('admin');
        $this->validateOnly('newPassword');
        $account = Account::findOrFail($this->passwordFor);
        $this->manager()->resetPassword($account, $this->newPassword, auth()->user());
        $this->reset('newPassword', 'newPassword_confirmation', 'passwordFor');
        $this->dispatch('close-modal', name: 'user-password');
        $this->dispatch('toast', type: 'success', message: "Password reset for {$account->username}.");
    }

    public function setDisabled(int $id, bool $disabled): void
    {
        $this->authorize('admin');
        try {
            $this->manager()->setDisabled(Account::findOrFail($id), $disabled, auth()->user());
            $this->dispatch('toast', type: 'success', message: $disabled ? 'User disabled.' : 'User enabled.');
        } catch (InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function toggleUpdateGbif(int $id): void
    {
        $this->authorize('admin');
        $account = Account::findOrFail($id);
        $on = ! $account->canUpdateGbif();
        $this->manager()->setUpdateGbif($account, $on, auth()->user());
        $this->dispatch('toast', type: 'success', message: "update_gbif ".($on ? 'granted to' : 'revoked from')." {$account->username}.");
    }

    public function render()
    {
        return view('livewire.admin.users', ['flagNames' => AccountManager::FLAGS, ...$this->tableData()]);
    }
}
