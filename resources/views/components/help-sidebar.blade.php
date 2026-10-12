@props(['route' => request()->route()?->getName()])
@php $partial = \App\Support\Help::partialFor($route); @endphp
@if ($partial)
<aside x-data="{ open: localStorage.getItem('gbif-help-open') !== '0' }"
       x-init="$watch('open', v => localStorage.setItem('gbif-help-open', v ? '1' : '0'))"
       :class="open ? 'lg:w-80' : 'lg:w-10'"
       class="w-full shrink-0 lg:sticky lg:top-4 lg:self-start" data-help-sidebar="{{ $partial }}" aria-label="Guide for this page">
    <div class="rounded-lg border border-emerald-200 bg-emerald-50/60">
        <button type="button" x-on:click="open = ! open" :aria-expanded="open"
                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm font-semibold text-emerald-900">
            <span x-show="open" class="flex items-center gap-2"><span aria-hidden="true">?</span> Guide for this page</span>
            <span x-show="! open" x-cloak class="mx-auto lg:[writing-mode:vertical-rl]" title="Show guide">? Guide</span>
            <span x-show="open" aria-hidden="true" class="text-emerald-700">&#x2715;</span>
        </button>
        <div x-show="open" x-cloak class="help-content space-y-3 border-t border-emerald-200 px-4 py-3 text-sm leading-relaxed text-slate-700
                    [&_h3]:mt-2 [&_h3]:font-semibold [&_h3]:text-slate-900 [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ol]:pl-5
                    [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5 [&_strong]:text-slate-900 [&_em]:text-slate-600">
            @include($partial)
            @unless ($partial === 'help.login')
                <details class="rounded-md border border-emerald-200 bg-white/70 px-3 py-2" data-help-workflow>
                    <summary class="cursor-pointer font-medium text-emerald-900">Typical workflow</summary>
                    @include('help._workflow')
                </details>
            @endunless
        </div>
    </div>
</aside>
@endif
