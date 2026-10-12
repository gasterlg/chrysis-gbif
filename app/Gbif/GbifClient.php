<?php

namespace App\Gbif;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The only place that talks to api.gbif.org. Server-side only.
 * Retries on 429/5xx/connection errors with exponential backoff (honours
 * Retry-After), throttles to about config('gbif.rate_per_second') requests per
 * second, and caches responses.
 */
class GbifClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userAgent,
        private readonly int $timeout = 30,
        private readonly int $retries = 5,
        private readonly int $baseBackoffMs = 500,
        private readonly int $ratePerSecond = 2,
        private readonly int $matchTtl = 2592000,      // 30 days
        private readonly int $occurrenceTtl = 3600,    // 1 hour
    ) {}

    public static function fromConfig(): self
    {
        $c = config('gbif');

        return new self(
            $c['base_url'], $c['user_agent'], $c['timeout'], $c['retries'],
            $c['backoff_ms'], $c['rate_per_second'], $c['cache']['match'], $c['cache']['occurrence'],
        );
    }

    /**
     * v2 species match. Returns a flat array:
     * usage_key, name, canonical_name, authorship, rank, status, match_type,
     * confidence, accepted_key, accepted_name, family, raw.
     */
    public function matchSpecies(string $scientificName, array $hints = []): array
    {
        $query = array_filter(['scientificName' => trim($scientificName)] + $hints + ['verbose' => 'true'], fn ($v) => $v !== null && $v !== '');
        $key = 'gbif:match:'.md5(json_encode($query));

        return Cache::remember($key, $this->matchTtl, function () use ($query) {
            $json = $this->get('/v2/species/match', $query);

            $usage = $json['usage'] ?? null;
            $accepted = $json['acceptedUsage'] ?? null;
            $family = collect($json['classification'] ?? [])->firstWhere('rank', 'FAMILY');

            return [
                'usage_key' => isset($usage['key']) ? (int) $usage['key'] : null,
                'name' => $usage['name'] ?? null,
                'canonical_name' => $usage['canonicalName'] ?? null,
                'authorship' => $usage['authorship'] ?? null,
                'rank' => $usage['rank'] ?? null,
                'status' => $usage['status'] ?? null,
                'match_type' => $json['diagnostics']['matchType'] ?? 'NONE',
                'confidence' => $json['diagnostics']['confidence'] ?? null,
                'accepted_key' => isset($accepted['key']) ? (int) $accepted['key'] : null,
                'accepted_name' => $accepted['name'] ?? null,
                'accepted_canonical_name' => $accepted['canonicalName'] ?? null,
                'family' => $family['name'] ?? null,
                'note' => $json['diagnostics']['note'] ?? null,
                'alternatives' => array_values(array_map(fn ($a) => [
                    'key' => isset($a['usage']['key']) ? (int) $a['usage']['key'] : null,
                    'name' => $a['usage']['name'] ?? null,
                    'canonical_name' => $a['usage']['canonicalName'] ?? null,
                    'rank' => $a['usage']['rank'] ?? null,
                    'status' => $a['usage']['status'] ?? null,
                    'accepted_key' => isset($a['acceptedUsage']['key']) ? (int) $a['acceptedUsage']['key'] : null,
                    'match_type' => $a['diagnostics']['matchType'] ?? null,
                    'confidence' => $a['diagnostics']['confidence'] ?? null,
                ], $json['diagnostics']['alternatives'] ?? [])),
                'raw' => $json,
            ];
        });
    }

    /** v1 dataset metadata (cached 7 days). */
    public function dataset(string $key): array
    {
        return Cache::remember("gbif:dataset:{$key}", 604800, fn () => $this->get("/v1/dataset/{$key}", []));
    }

    /** v1 species lookup by key (for manually entered keys). Null when GBIF has no such taxon. */
    public function species(int $key): ?array
    {
        return Cache::remember("gbif:species:{$key}", $this->matchTtl, function () use ($key) {
            try {
                $json = $this->get("/v1/species/{$key}", []);
            } catch (GbifException $e) {
                if ($e->getCode() === 404) {
                    return null;
                }
                throw $e;
            }

            return [
                'key' => (int) $json['key'],
                'name' => $json['scientificName'] ?? null,
                'canonical_name' => $json['canonicalName'] ?? null,
                'rank' => $json['rank'] ?? null,
                'status' => $json['taxonomicStatus'] ?? null,
                'accepted_key' => isset($json['acceptedKey']) ? (int) $json['acceptedKey'] : null,
                'family' => $json['family'] ?? null,
            ];
        });
    }

    /**
     * v1 occurrence search (paged; GBIF caps limit at 300 and offset+limit at 100,000).
     * Returns ['count' => int, 'end_of_records' => bool, 'offset' => int, 'limit' => int, 'results' => array].
     */
    public function searchOccurrences(array $filters, int $limit = 20, int $offset = 0, bool $cache = true): array
    {
        $limit = max(0, min($limit, 300));
        if ($offset + $limit > 100000) {
            throw new GbifException('GBIF occurrence search cannot page beyond 100,000 records; use the Download API.');
        }

        $query = $filters + ['limit' => $limit, 'offset' => $offset];
        ksort($query);
        $key = 'gbif:occ:'.md5(json_encode($query));

        $fetch = function () use ($query) {
            $json = $this->get('/v1/occurrence/search', $query);

            return [
                'count' => (int) ($json['count'] ?? 0),
                'end_of_records' => (bool) ($json['endOfRecords'] ?? true),
                'offset' => (int) ($json['offset'] ?? 0),
                'limit' => (int) ($json['limit'] ?? 0),
                'results' => $json['results'] ?? [],
            ];
        };

        // Bulk imports pass cache: false so large pages do not fill the cache table.
        return $cache ? Cache::remember($key, $this->occurrenceTtl, $fetch) : $fetch();
    }

    /** Total occurrence count for a taxon key (limit=0 request). */
    public function occurrenceCount(int $taxonKey): int
    {
        return $this->searchOccurrences(['taxon_key' => $taxonKey], 0)['count'];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withUserAgent($this->userAgent)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(
                $this->retries,
                function (int $attempt, \Throwable $e) {
                    if ($e instanceof RequestException && ($after = $e->response->header('Retry-After')) !== '' && is_numeric($after)) {
                        return min((int) $after * 1000, 60000);
                    }

                    return $this->baseBackoffMs * (2 ** ($attempt - 1));
                },
                fn (\Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())),
            );
    }

    private function get(string $path, array $query): array
    {
        $this->throttle();

        try {
            /** @var Response $response */
            $response = $this->request()->get($path, $query);
            $response->throw();
        } catch (RequestException $e) {
            throw new GbifException("GBIF {$path} failed with HTTP {$e->response->status()}", $e->response->status(), $e);
        } catch (ConnectionException $e) {
            throw new GbifException("GBIF {$path} unreachable: {$e->getMessage()}", 0, $e);
        }

        return $response->json() ?? [];
    }

    /** Simple process-wide throttle: about N requests per second. */
    private function throttle(): void
    {
        if ($this->ratePerSecond <= 0) {
            return;
        }

        while (! RateLimiter::attempt('gbif-api', $this->ratePerSecond, fn () => true, 1)) {
            usleep(200_000);
        }
    }
}
