<?php

namespace App\Livewire\Data;

use App\Gbif\DownloadClient;
use App\Gbif\GbifException;
use App\Gbif\OccurrenceExporter;
use App\Models\AuditLog;
use App\Models\GbifImportRun;
use App\Models\Species;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Exports')]
class Exports extends Component
{
    public string $species = '';

    public function export()
    {
        $idsp = $this->species !== '' ? (int) $this->species : null;
        $exporter = app(OccurrenceExporter::class);
        $count = $exporter->query($idsp)->count();
        if ($count === 0) {
            $this->dispatch('toast', type: 'warning', message: 'No approved records to export yet.');

            return null;
        }
        AuditLog::record('export_occurrences', auth()->id(), auth()->user()->username, $idsp ? "idsp:{$idsp}" : 'all', ['records' => $count]);

        return $exporter->download($idsp);
    }

    public function requestDownload(DownloadClient $client): void
    {
        $this->authorize('update-gbif');
        try {
            $run = $client->request([], auth()->id());
            AuditLog::record('gbif_download_requested', auth()->id(), auth()->user()->username, null, ['run' => $run->id, 'key' => $run->download_key]);
            $this->dispatch('toast', type: 'success', message: "GBIF download {$run->download_key} requested.");
        } catch (GbifException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function poll(int $runId, DownloadClient $client): void
    {
        $client->poll(GbifImportRun::where('type', 'download')->findOrFail($runId));
    }

    public function render()
    {
        $approved = DB::connection('chrysis')->table('gbif_occurrences as o')->join('_specie as s', 's.IDSP', '=', 'o.idsp')
            ->where('o.state', 'approved')->groupBy('o.idsp', 's.GEN', 's.SP', 's.SSP')->orderBy('s.GEN')->orderBy('s.SP')
            ->selectRaw('o.idsp, s.GEN, s.SP, s.SSP, COUNT(*) n')->get();

        return view('livewire.data.exports', [
            'options' => $approved->mapWithKeys(fn ($r) => [$r->idsp => trim(implode(' ', array_filter([$r->GEN, Species::clean($r->SP), Species::clean($r->SSP)])))." ({$r->n})"])->all(),
            'approvedTotal' => (int) $approved->sum('n'),
            'pendingTotal' => DB::connection('chrysis')->table('gbif_occurrences')->where('state', 'pending')->count(),
            'downloadsEnabled' => DownloadClient::configured(),
            'downloads' => GbifImportRun::where('type', 'download')->latest('id')->limit(10)->get(),
        ]);
    }
}
