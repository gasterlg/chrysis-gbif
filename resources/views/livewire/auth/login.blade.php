<div class="w-full max-w-sm">
    <div class="mb-6 text-center">
        <div class="mx-auto mb-3 h-10 w-10 rounded-full bg-gradient-to-br from-emerald-500 to-sky-600"></div>
        <h1 class="text-xl font-semibold">{{ config('app.name') }}</h1>
        <p class="text-sm text-slate-500">Sign in with your chrysis.net admin account</p>
    </div>
    <form wire:submit="login" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" novalidate>
        <x-form.input name="form.login" label="Username or e-mail" autocomplete="username" autofocus />
        <x-form.input name="form.password" label="Password" type="password" autocomplete="current-password" />
        <x-button type="submit" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Log in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </x-button>
    </form>
</div>
