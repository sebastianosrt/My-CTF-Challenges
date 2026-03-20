<?php

namespace Herbarium\Specimens;

use Herbarium\Core\Database;

class SpecimenExporter
{
    private array $specimens;

    private static array $csvColumns = [
        'id', 'common_name', 'species', 'family', 'genus',
        'location_found', 'habitat', 'collected_date', 'collector',
        'description', 'preservation_method', 'source', 'imported_at',
    ];

    public function __construct(array $specimens)
    {
        $this->specimens = $specimens;
    }

    public static function fromQuery(?string $search = null): self
    {
        $where  = '';
        $params = [];
        if ($search !== null && $search !== '') {
            $like   = "%{$search}%";
            $where  = "WHERE common_name LIKE ? OR species LIKE ? OR family LIKE ?";
            $params = [$like, $like, $like];
        }

        $rows = Database::prepared("SELECT * FROM specimens {$where} ORDER BY id ASC", $params);
        return new self($rows);
    }

    public function count(): int
    {
        return count($this->specimens);
    }

    public function toJson(): string
    {
        return json_encode([
            'exported_at' => date('Y-m-d\TH:i:s\Z'),
            'count'       => $this->count(),
            'specimens'   => $this->specimens,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function toCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::$csvColumns);

        foreach ($this->specimens as $row) {
            $line = [];
            foreach (self::$csvColumns as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function toXml(): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('herbarium');
        $dom->appendChild($root);

        foreach ($this->specimens as $row) {
            $node = $dom->createElement('specimen');
            foreach (['common_name','species','family','genus','location_found','habitat','collected_date','collector','description','preservation_method'] as $field) {
                $el = $dom->createElement($field);
                $el->appendChild($dom->createTextNode($row[$field] ?? ''));
                $node->appendChild($el);
            }
            $root->appendChild($node);
        }

        return $dom->saveXML();
    }

    public function filter(callable $fn): int
    {
        $before = count($this->specimens);
        $this->specimens = array_values(array_filter($this->specimens, $fn));
        return $before - count($this->specimens);
    }

    public function toArray(): array
    {
        return $this->specimens;
    }

    public function summarize(): array
    {
        $families = [];
        $genera   = [];
        $dates    = [];

        foreach ($this->specimens as $s) {
            if (!empty($s['family'])) {
                $families[$s['family']] = true;
            }
            if (!empty($s['genus'])) {
                $genera[$s['genus']] = true;
            }
            if (!empty($s['collected_date'])) {
                $dates[] = $s['collected_date'];
            }
        }

        sort($dates);

        return [
            'total'    => count($this->specimens),
            'families' => count($families),
            'genera'   => count($genera),
            'earliest' => !empty($dates) ? $dates[0] : null,
            'latest'   => !empty($dates) ? end($dates) : null,
        ];
    }
}
