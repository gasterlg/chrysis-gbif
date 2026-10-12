@props(['points' => [], 'height' => '420px'])
{{-- Leaflet is vendored in public/vendor/leaflet (no CDN); tiles from OpenStreetMap. points: [[lat, lon, gbifId, label, year], …] --}}
<div wire:ignore x-data="{
        init() {
            const pts = @js($points);
            const map = L.map(this.$refs.map, { worldCopyJump: true }).setView([45, 12], 4);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
            if (pts.length) {
                const layer = L.featureGroup(pts.map(p => L.circleMarker([p[0], p[1]], { radius: 4, weight: 1, color: '#065f46', fillColor: '#10b981', fillOpacity: .7 })
                    .bindPopup(`<a href='https://www.gbif.org/occurrence/${p[2]}' target='_blank' rel='noopener'>${p[2]}</a><br>${(p[3] || '').replace(/[<>&]/g, '')} ${p[4] ?? ''}`))).addTo(map);
                map.fitBounds(layer.getBounds().pad(0.1), { maxZoom: 9 });
            }
            setTimeout(() => map.invalidateSize(), 100);
        }
     }" {{ $attributes->class('overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm') }} data-map-points="{{ count($points) }}">
    <div x-ref="map" style="height: {{ $height }}"></div>
</div>
