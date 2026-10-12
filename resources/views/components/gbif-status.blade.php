@props(['status'])
@switch($status)
    @case('matched') <x-badge color="green" data-status="matched">Matched</x-badge> @break
    @case('failed') <x-badge color="red" data-status="failed" title="match.gbif_id = 0: the old Chrawler lookup failed">Failed</x-badge> @break
    @default <x-badge color="amber" data-status="none">No match row</x-badge>
@endswitch
