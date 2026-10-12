<span class="inline-flex gap-1">
    <button type="button" wire:click="edit({{ $r->id }})" class="rounded border border-slate-300 px-2 py-0.5 text-xs hover:bg-slate-50">Edit</button>
    <button type="button" wire:click="askPassword({{ $r->id }})" class="rounded border border-slate-300 px-2 py-0.5 text-xs hover:bg-slate-50">Reset password</button>
    @if ($r->id !== $me)
        @if ($disabled)
            <button type="button" wire:click="setDisabled({{ $r->id }}, false)" class="rounded border border-emerald-600 px-2 py-0.5 text-xs text-emerald-800 hover:bg-emerald-50">Enable</button>
        @else
            <x-confirm-button method="setDisabled" :args="[$r->id, true]" danger title="Disable {{ $r->username }}?" message="They can no longer log in to this app. /admin (legacy) is not affected." confirm-label="Disable" class="!px-2 !py-0.5 text-xs">Disable</x-confirm-button>
        @endif
    @endif
</span>
