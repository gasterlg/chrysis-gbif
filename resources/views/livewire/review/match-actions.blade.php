@if (in_array($r->status, ['pending', 'rejected', 'rolled_back', 'same'], true))
    <div class="inline-flex gap-1">
        @if ($r->proposed_key && $r->status !== 'same')
            <button type="button" wire:click="accept({{ $r->id }})" class="rounded border border-emerald-600 px-2 py-0.5 text-xs text-emerald-800 hover:bg-emerald-50" data-accept="{{ $r->id }}">Accept</button>
        @endif
        <button type="button" wire:click="review({{ $r->id }})" class="rounded border border-slate-300 px-2 py-0.5 text-xs hover:bg-slate-50">Review…</button>
        @if ($r->status === 'pending')
            <button type="button" wire:click="reject({{ $r->id }})" class="rounded border border-red-300 px-2 py-0.5 text-xs text-red-700 hover:bg-red-50">Reject</button>
        @endif
    </div>
@endif
