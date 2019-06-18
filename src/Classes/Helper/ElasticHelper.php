<?php

namespace Helio\Panel\Helper;

use Elasticsearch\ClientBuilder;

class ElasticHelper
{
    /** @var \Elasticsearch\Client */
    protected $client;
    protected $from = 0;
    protected $size = 10;

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
     * @return array
     */
    public function getLogEntries(int $userId, int $jobId = null, int $taskId = null): array
    {
        $params = [
            'index' => vsprintf(self::$indexTemplate, [$userId]),
            'body' => [
                '_source' => [self::$logEntryFieldName, self::$timestampFieldName],
                'from' => $this->getFrom(),
                'size' => $this->getSize(),
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
                // TODO: This is a workaround until we have a proper field `task_id` : $filter[] = ['term' => [self::$taskIdFieldName => (string)$taskId]];
                $filter[] = ['wildcard' => [
                    'container_name.keyword' => '*/*' . ($jobId ? "-$jobId" : '') . "-$taskId.*"]];
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

    /**
     * @return int
     */
    public function getFrom(): int
    {
        return $this->from;
    }

    /**
     * @param int $from
     * @return ElasticHelper
     */
    public function setFrom(int $from): ElasticHelper
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param int $size
     * @return ElasticHelper
     */
    public function setSize(int $size): ElasticHelper
    {
        $this->size = $size;
        return $this;
    }
}
