<?php

namespace Herbarium\Specimens;

use Herbarium\Import\HttpFetcher;

class TaxonomyLookup
{
    private HttpFetcher $http;
    private string $baseUrl = 'https://api.gbif.org/v1';

    public function __construct(?HttpFetcher $http = null)
    {
        $this->http = $http ?? new HttpFetcher();
    }

    public function search(string $query, int $limit = 5): array
    {
        $url = $this->baseUrl . '/species/search?' . http_build_query([
            'q'      => $query,
            'limit'  => $limit,
            'rank'   => 'SPECIES',
            'status' => 'ACCEPTED',
        ]);

        try {
            $body    = $this->http->get($url, ['Accept' => 'application/json']);
            $data    = json_decode($body, true);
            $results = [];

            foreach (($data['results'] ?? []) as $item) {
                $results[] = [
                    'key'            => $item['key'] ?? null,
                    'scientific_name' => $item['scientificName'] ?? $item['canonicalName'] ?? '',
                    'kingdom'        => $item['kingdom'] ?? '',
                    'phylum'         => $item['phylum'] ?? '',
                    'class'          => $item['class'] ?? '',
                    'order'          => $item['order'] ?? '',
                    'family'         => $item['family'] ?? '',
                    'genus'          => $item['genus'] ?? '',
                    'species'        => $item['species'] ?? '',
                    'status'         => $item['taxonomicStatus'] ?? '',
                    'source'         => 'gbif',
                ];
            }

            return ['results' => $results, 'error' => null];
        } catch (\RuntimeException $e) {
            return ['results' => [], 'error' => $e->getMessage()];
        }
    }

    public function detail(int $speciesKey): array
    {
        $url = $this->baseUrl . '/species/' . $speciesKey;

        try {
            $body = $this->http->get($url, ['Accept' => 'application/json']);
            $item = json_decode($body, true);

            if (empty($item) || isset($item['error'])) {
                return ['detail' => null, 'error' => $item['error'] ?? 'Not found'];
            }

            return [
                'detail' => [
                    'key'             => $item['key'] ?? null,
                    'scientific_name' => $item['scientificName'] ?? '',
                    'canonical_name'  => $item['canonicalName'] ?? '',
                    'kingdom'         => $item['kingdom'] ?? '',
                    'phylum'          => $item['phylum'] ?? '',
                    'class'           => $item['class'] ?? '',
                    'order'           => $item['order'] ?? '',
                    'family'          => $item['family'] ?? '',
                    'genus'           => $item['genus'] ?? '',
                    'species'         => $item['species'] ?? '',
                    'status'          => $item['taxonomicStatus'] ?? '',
                    'according_to'    => $item['accordingTo'] ?? '',
                    'published_in'    => $item['publishedIn'] ?? '',
                    'source'          => 'gbif',
                ],
                'error' => null,
            ];
        } catch (\RuntimeException $e) {
            return ['detail' => null, 'error' => $e->getMessage()];
        }
    }

    public function suggest(string $prefix, int $limit = 10): array
    {
        $url = $this->baseUrl . '/species/suggest?' . http_build_query([
            'q'     => $prefix,
            'limit' => $limit,
        ]);

        try {
            $body    = $this->http->get($url, ['Accept' => 'application/json']);
            $items   = json_decode($body, true) ?: [];
            $results = [];

            foreach ($items as $item) {
                $results[] = [
                    'key'             => $item['key'] ?? null,
                    'scientific_name' => $item['scientificName'] ?? $item['canonicalName'] ?? '',
                    'rank'            => $item['rank'] ?? '',
                    'family'          => $item['family'] ?? '',
                    'genus'           => $item['genus'] ?? '',
                    'status'          => $item['status'] ?? '',
                ];
            }

            return ['suggestions' => $results, 'error' => null];
        } catch (\RuntimeException $e) {
            return ['suggestions' => [], 'error' => $e->getMessage()];
        }
    }

    public function classify(int $speciesKey): array
    {
        $url = $this->baseUrl . '/species/' . $speciesKey . '/parents';

        try {
            $body  = $this->http->get($url, ['Accept' => 'application/json']);
            $items = json_decode($body, true) ?: [];
            $chain = [];

            foreach ($items as $item) {
                $chain[] = [
                    'key'             => $item['key'] ?? null,
                    'scientific_name' => $item['scientificName'] ?? '',
                    'rank'            => $item['rank'] ?? '',
                ];
            }

            return ['hierarchy' => $chain, 'error' => null];
        } catch (\RuntimeException $e) {
            return ['hierarchy' => [], 'error' => $e->getMessage()];
        }
    }

    public function matchVernacular(string $commonName): array
    {
        $url = $this->baseUrl . '/species/match?' . http_build_query([
            'name'    => $commonName,
            'verbose' => 'true',
            'kingdom' => 'Plantae',
        ]);

        try {
            $body = $this->http->get($url, ['Accept' => 'application/json']);
            $item = json_decode($body, true);

            if (empty($item) || ($item['matchType'] ?? '') === 'NONE') {
                return ['match' => null, 'error' => 'No match found'];
            }

            return [
                'match' => [
                    'key'             => $item['usageKey'] ?? null,
                    'scientific_name' => $item['scientificName'] ?? '',
                    'confidence'      => $item['confidence'] ?? 0,
                    'match_type'      => $item['matchType'] ?? '',
                    'family'          => $item['family'] ?? '',
                    'genus'           => $item['genus'] ?? '',
                ],
                'error' => null,
            ];
        } catch (\RuntimeException $e) {
            return ['match' => null, 'error' => $e->getMessage()];
        }
    }
}
