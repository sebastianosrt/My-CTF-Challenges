<?php

use Herbarium\Core\Database;
use Herbarium\Import\ImportPipeline;
use Herbarium\Import\ImportPipelineRegistry;
use Herbarium\Specimens\SpecimenCollector;

function parseSpecimensXml(string $xmlContent, int $userId, string $source, int $xmlFlags = LIBXML_NONET): array
{
    $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', $xmlFlags);

    if ($xml === false) {
        return ['error' => 'Failed to parse XML', 'count' => 0];
    }

    $rows = [];
    foreach ($xml->specimen as $node) {
        $rows[] = [
            'common_name'         => (string) ($node->common_name ?? ''),
            'species'             => (string) ($node->species ?? ''),
            'family'              => (string) ($node->family ?? ''),
            'genus'               => (string) ($node->genus ?? ''),
            'location_found'      => (string) ($node->location_found ?? ''),
            'habitat'             => (string) ($node->habitat ?? ''),
            'collected_date'      => (string) ($node->collected_date ?? ''),
            'collector'           => (string) ($node->collector ?? ''),
            'description'         => (string) ($node->description ?? ''),
            'preservation_method' => (string) ($node->preservation_method ?? ''),
        ];
    }

    $pipeline = new ImportPipeline([
        'required_fields'   => ['common_name'],
        'default_collector' => 'Herbarium Import',
        'allow_duplicates'  => false,
    ]);

    $processorRegistry = new ImportPipelineRegistry(
        [
            'sanitize'    => \Herbarium\Processors\SanitizationProcessor::class,
            'normalize'   => \Herbarium\Processors\NormalizationProcessor::class,
            'validate'    => \Herbarium\Processors\ValidationProcessor::class,
            'enrich'      => \Herbarium\Processors\EnrichmentProcessor::class,
            'deduplicate' => \Herbarium\Processors\DeduplicationProcessor::class,
        ],
        ['source' => $source, 'user_id' => $userId],   
        $rows,                                           
        $pipeline
    );

    $batch = $processorRegistry->runBatch($rows);

    $collector = new SpecimenCollector([], $userId, $source);
    foreach ($batch['accepted'] as $specimen) {
        $collector->add($specimen);
    }

    $count = $collector->flush();

    return ['error' => null, 'count' => $count, 'rejected' => $batch['rejected']];
}

function logImport(int $userId, string $sourceType, string $sourceDetail, int $count, string $status = 'success'): void
{
    Database::preparedExec(
        "INSERT INTO import_logs (user_id, source_type, source_detail, records_imported, status)
        VALUES (?, ?, ?, ?, ?)",
        [$userId, $sourceType, $sourceDetail, $count, $status]
    );
}
