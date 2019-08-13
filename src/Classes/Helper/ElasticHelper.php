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
    protected $from = 0;
    protected $size = 10;

    /**
     * @var array<ElasticHelper>
     */
    protected static $instances;

    /**
     * @var string
     */
    protected static $indexTemplate = 'log_user_%s';
    protected static $jobIdFieldName = 'HELIO_JOBID';
    protected static $executionIdFieldName = 'HELIO_EXECUTIONID';
    protected static $logEntryFieldName = 'log';
    protected static $timestampFieldName = '@timestamp';

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
     * @param bool     $cleanSource wether or not to only display clean fields
     *
     * @return array
     */
    public function getLogEntries(int $userId, int $jobId = null, int $executionId = null, bool $cleanSource = true): array
    {
        $params = [
            'index' => vsprintf(self::$indexTemplate, [$userId]),
            'body' => [
                'from' => $this->getFrom(),
                'size' => $this->getSize(),
                'sort' => [
                    self::$timestampFieldName => 'desc',
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

        if ($cleanSource) {
            $params['body']['_source'] = [self::$logEntryFieldName, self::$timestampFieldName];
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
            return $this->client->search($params)['hits'];
        } catch (Exception $e) {
            LogHelper::warn('Error in Elastic Query: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param int $userId
     *
     * @return array
     */
    public function getWeirdLogEntries(int $userId): array
    {
        return $this->getLogEntries($userId, -1, -1, false);
    }

    /**
     * @return int
     */
    protected function getFrom(): int
    {
        return $this->from;
    }

    /**
     * @param int $from
     *
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
    protected function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param int $size
     *
     * @return ElasticHelper
     */
    public function setSize(int $size): ElasticHelper
    {
        $this->size = $size;

        return $this;
    }
}
