<?php

namespace Helio\Panel\Service;

use Helio\Panel\Helper\ElasticHelper;
use Helio\Panel\Request\Log;

class LogService
{
    /**
     * @var ElasticHelper
     */
    private $elasticHelper;

    public function __construct(ElasticHelper $elasticHelper)
    {
        $this->elasticHelper = $elasticHelper;
    }

    public function retrieveLogs(int $userId, Log $log, int $jobId = null, int $executionId = null)
    {
        $firstCursor = null;
        $lastCursor = null;

        $data = $this->elasticHelper->getLogEntries(
            $userId,
            $jobId,
            $executionId,
            $log->getFrom(),
            $log->getSize(),
            $log->getSort(),
            $log->getCursor()
        );

        ['total' => $total, 'hits' => $hits] = $data;
        $total = $total ?? 0;
        $hits = $hits ?? [];

        // ES 7 switches from single value to an object with {value, relation}
        // https://www.elastic.co/guide/en/elasticsearch/reference/current/breaking-changes-7.0.html
        if (is_array($total)) {
            $total = $total['value'];
        }

        $hitCount = count($hits);
        if ($hitCount) {
            $firstCursor = $hits[0]['sort'][0];
            $lastCursor = $hits[$hitCount - 1]['sort'][0];
        }

        $logs = array_map(function ($entry) {
            $source = $entry['_source'];

            return [
                'timestamp' => $source[ElasticHelper::$timestampFieldName],
                'message' => $source[ElasticHelper::$logEntryFieldName],
                'source' => $source[ElasticHelper::$sourceFieldName],
                'executionId' => $source[ElasticHelper::$executionIdFieldName],
                'jobId' => $source[ElasticHelper::$jobIdFieldName],
            ];
        }, $hits);

        return [
            'total' => $total,
            'logs' => $logs,
            'cursor' => [
                'first' => (string) $firstCursor,
                'last' => (string) $lastCursor,
            ],
        ];
    }
}
