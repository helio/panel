<?php

namespace Helio\Panel\Helper;

use Elasticsearch\ClientBuilder;

class ElasticHelper
{
    /** @var \Elasticsearch\Client */
    protected $client;

    /**
     * @var string
     */
    protected static $indexTemplate = 'log_user_%s';
    protected static $jobIdFieldName = 'job_id';
    protected static $taskIdFieldName = 'task_id';
    protected static $logEntryFieldName = 'log';
    protected static $timestampFieldName = '@timestamp';


    /**
     * ElasticHelper constructor.
     * @param array $hosts
     */
    public function __construct(array $hosts = [])
    {
        $this->client = ClientBuilder::create()
            ->setHosts([$hosts])
            ->setRetries(2)
            ->build();
    }


    /**
     * @param int $userId
     * @param int|null $jobId negative value means the field must not exist
     * @param int|null $taskId negative value means the field must not exist
     * @param int $from
     * @param int $size
     * @return array
     */
    public function getLogEntries(int $userId, int $jobId = null, int $taskId = null, int $from = 0, int $size = 10): array
    {
        $params = [
            'index' => vsprintf(self::$indexTemplate, [$userId]),
            'body' => [
                '_source' => [self::$logEntryFieldName, self::$timestampFieldName],
                'from' => $from,
                'size' => $size,
                'sort' => [
                    self::$timestampFieldName => 'desc'
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            'exists' => [
                                'field' => self::$logEntryFieldName
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $filter = [];
        $mustNot = [];
        if ($jobId) {
            if ($jobId < 0) {
                $mustNot[] = ['exists' => ['field' => self::$jobIdFieldName]];
            } else {
                $filter[] = ['term' => [self::$jobIdFieldName => $jobId]];
            }
        }
        if ($taskId) {
            if ($taskId < 0) {
                $mustNot[] = ['exists' => ['field' => self::$taskIdFieldName]];
            } else {
                $filter[] = ['term' => [self::$taskIdFieldName => (string)$taskId]];
            }
        }

        if ($filter) {
            $params['body']['query']['bool']['must'] = $filter;
        }
        if ($mustNot) {
            $params['body']['query']['bool']['must_not'] = $mustNot;
        }

        LogHelper::debug('Running Elastic Query: ' . json_encode($params));
        try {
            return $this->client->search($params)['hits'];
        } catch (\Exception $e) {
            LogHelper::warn('Error in Elastic Query: ' . $e->getMessage());
            return [];
        }
    }
}
