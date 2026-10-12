<div>
    <x-page-header title="Users" subtitle="Accounts in chrysis_1.accounts_api (shared with /admin). Admin only; every change is audit-logged."
                   :breadcrumbs="['Dashboard' => route('home'), 'Users' => null]">
        <x-slot:actions><x-button wire:click="create" data-new-user>New user</x-button></x-slot:actions>
    </x-page-header>

    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected" search-placeholder="Search username, name, e-mail…" />

    <x-modal name="user-form" :title="$form->id ? 'Edit user' : 'New user'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-3" novalidate>
            <div class="grid grid-cols-2 gap-3">
                <x-form.input name="form.username" label="Username" />
                <x-form.select name="form.role" label="Role" :options="['SuperUser' => 'SuperUser', 'Admin' => 'Admin']" />
                <x-form.input name="form.first_name" label="First name" />
                <x-form.input name="form.last_name" label="Last name" />
                <x-form.input name="form.email" label="E-mail" type="email" class="col-span-2" />
                <x-form.input name="form.password" type="password" :label="$form->id ? 'New password (optional)' : 'Password'" help="At least 10 characters." autocomplete="new-password" />
                <x-form.input name="form.password_confirmation" type="password" label="Repeat password" autocomplete="new-password" />
            </div>
            <fieldset>
                <legend class="mb-1 text-sm font-medium text-slate-700">Legacy /admin permissions</legend>
                <div class="grid grid-cols-3 gap-x-3 gap-y-1">
                    @foreach ($flagNames as $f)
                        <x-form.checkbox name="form.flags.{{ $f }}" :label="str_replace('_', ' ', $f)" />
                    @endforeach
                </div>
            </fieldset>
            <div class="flex justify-end gap-2 pt-2">
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', { name: 'user-form' })">Cancel</x-button>
                <x-button type="submit">Save</x-button>
            </div>
        </form>
    </x-modal>

    <x-modal name="user-password" title="Reset password">
        <form wire:submit="resetPassword" class="space-y-3" novalidate>
            <x-form.input name="newPassword" type="password" label="New password" help="At least 10 characters. Stored as bcrypt ($2y$)." autocomplete="new-password" />
            <x-form.input name="newPassword_confirmation" type="password" label="Repeat password" autocomplete="new-password" />
            <div class="flex justify-end gap-2 pt-2">
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', { name: 'user-password' })">Cancel</x-button>
                <x-button type="submit">Reset password</x-button>
            </div>
        </form>
    </x-modal>
</div>
