{{-- Listens for $this->dispatch('toast', type: 'success|warning|error', message: '…') and session('toast'). --}}
<div x-data="{ toasts: [], n: 0,
               add(t) { const id = ++this.n; this.toasts.push({ id, type: t.type ?? 'success', message: t.message ?? '' }); setTimeout(() => this.remove(id), t.timeout ?? 5000) },
               remove(id) { this.toasts = this.toasts.filter(t => t.id !== id) } }"
     x-init="@if (session('toast')) add(@js(session('toast'))) @endif"
     x-on:toast.window="add(Array.isArray($event.detail) ? $event.detail[0] : $event.detail)"
     class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 flex-col gap-2" aria-live="polite" data-toasts>
    <template x-for="t in toasts" :key="t.id">
        <div class="pointer-events-auto flex items-start gap-2 rounded-md px-4 py-3 text-sm shadow-lg ring-1"
             :class="{ 'bg-emerald-50 text-emerald-900 ring-emerald-200': t.type === 'success', 'bg-amber-50 text-amber-900 ring-amber-200': t.type === 'warning', 'bg-red-50 text-red-900 ring-red-200': t.type === 'error' }">
            <span class="flex-1" x-text="t.message"></span>
            <button type="button" class="opacity-60 hover:opacity-100" x-on:click="remove(t.id)" aria-label="Dismiss">×</button>
        </div>
    </template>
</div>
