{{--
  One global confirm dialog (in the layout). Trigger from any Livewire view with:
  <x-confirm-button method="applyBatch" :args="[$id]" title="Apply batch?" message="…" danger>Apply</x-confirm-button>
  or from Alpine: $dispatch('confirm', { title, message, confirmLabel, danger, componentId: $wire.$id, method, args })
--}}
<div x-data="{ open: false, title: '', message: '', confirmLabel: 'Confirm', danger: false, componentId: null, method: null, args: [],
               ask(d) { Object.assign(this, { title: d.title ?? 'Are you sure?', message: d.message ?? '', confirmLabel: d.confirmLabel ?? 'Confirm', danger: !! d.danger, componentId: d.componentId, method: d.method, args: d.args ?? [] }); this.open = true },
               go() { this.open = false; if (this.componentId && this.method) Livewire.find(this.componentId).call(this.method, ...this.args) } }"
     x-on:confirm.window="ask($event.detail)" x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="alertdialog" aria-modal="true" data-confirm-dialog>
    <div class="absolute inset-0 bg-slate-900/40" x-on:click="open = false"></div>
    <div class="relative w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
        <h2 class="text-lg font-semibold" x-text="title"></h2>
        <p class="mt-2 text-sm text-slate-600" x-text="message"></p>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm" x-on:click="open = false">Cancel</button>
            <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-white" x-bind:class="danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-700 hover:bg-emerald-800'" x-on:click="go()" x-text="confirmLabel"></button>
        </div>
    </div>
</div>
