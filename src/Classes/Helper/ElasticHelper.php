<?php

namespace Helio\Panel\Helper;

use Exception;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Helio\Panel\Utility\ServerUtility;

/**
 * Class ElasticHelper.
 */
class ElasticHelper implements HelperInterface
{
    /** @var Client */
    protected $client;

    /**
     * @var array<ElasticHelper>
     */
    protected static $instances;

    /**
     * @var string
     */
    public static $indexTemplate = 'log_user_%s';
    public static $jobIdFieldName = 'HELIO_JOBID';
    public static $executionIdFieldName = 'HELIO_EXECUTIONID';
    public static $logEntryFieldName = 'log';
    public static $timestampFieldName = '@timestamp';
    public static $sourceFieldName = 'source';

    /**
     * ElasticHelper constructor.
     *
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
     * @return $this
     */
    public static function getInstance(): self
    {
        $class = static::class;
        if (!self::$instances || !array_key_exists($class, self::$instances)) {
            $host = ['host' => ServerUtility::get('ELASTIC_HOST')];
            foreach (['port', 'scheme', 'user', 'pass', 'path'] as $variableName) {
                if (ServerUtility::get('ELASTIC_' . strtoupper($variableName), '')) {
                    $host[$variableName] = ServerUtility::get('ELASTIC_' . strtoupper($variableName));
                }
            }
            self::$instances[$class] = new static($host);
        }

        return self::$instances[$class];
    }

    /**
     * @param int      $userId
     * @param int|null $jobId       negative value means the field must not exist
     * @param int|null $executionId negative value means the field must not exist
     * @param int      $from
     * @param int      $size
     * @param string   $sort
     * @param string   $cursor
     * @param bool     $cleanSource
     *
     * @return array
     */
    public function getLogEntries(
        int $userId,
        int $jobId = null,
        int $executionId = null,
        int $from = 0,
        int $size = 10,
        string $sort = 'desc',
        string $cursor = null,
        bool $cleanSource = true
    ): array {
        $params = [
            'index' => vsprintf(self::$indexTemplate, [$userId]),
            'body' => [
                'from' => $from,
                'size' => $size,
                'sort' => [
                    self::$timestampFieldName => $sort,
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            'exists' => [
                                'field' => self::$logEntryFieldName,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($cursor) {
            $params['body']['search_after'] = [$cursor];
        }

        if ($cleanSource) {
            $params['body']['_source'] = [
                self::$logEntryFieldName,
                self::$timestampFieldName,
                self::$sourceFieldName,
                self::$executionIdFieldName,
                self::$jobIdFieldName,
            ];
        }

        $filter = [];
        $mustNot = [];
        if ($jobId) {
            if ($jobId < 0) {
                $mustNot[] = ['exists' => ['field' => self::$jobIdFieldName]];
            } else {
                $filter[] = ['term' => [self::$jobIdFieldName => $jobId]];
            }
        }
        if ($executionId) {
            if ($executionId < 0) {
                $mustNot[] = ['exists' => ['field' => self::$executionIdFieldName]];
            } else {
                $filter[] = ['term' => [self::$executionIdFieldName => (string) $executionId]];
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
            $response = $this->client->search($params);

            return $response['hits'];
        } catch (Exception $e) {
            LogHelper::warn('Error in Elastic Query: ' . $e->getMessage());

            return [];
        }
    }
}
