<?php

namespace Helio\Test\Unit;

use Helio\Panel\Helper\ElasticHelper;
use Helio\Test\TestCase;

class ElasticHelperTest extends TestCase
{
    public function testSerializeLogEntries(): void
    {
        $esResponse = '{
    "total": {
      "value": 1,
      "relation": "eq"
    },
    "max_score": 1.0,
    "hits": [
      {
        "_index": "log_user_1",
        "_type": "_doc",
        "_id": "IAaEr2wBQfXgy-CCLNZH",
        "_score": 1.0,
        "_source": {
          "log": "test stdout",
          "@timestamp": "2019-08-20T12:16:02.000Z",
          "HELIO_EXECUTIONID": "1",
          "HELIO_JOBID": "74",
          "job_id": "74",
          "container_id": "yolo",
          "container_name": "yoloname",
          "source": "stdout"
        }
      }
    ]
  }';
        $input = \GuzzleHttp\json_decode($esResponse, true);
        $output = [
            'total' => 1,
            'logs' => [
                [
                    'timestamp' => '2019-08-20T12:16:02.000Z',
                    'message' => 'test stdout',
                    'source' => 'stdout',
                ],
            ],
        ];

        self::assertEquals($output, ElasticHelper::serializeLogEntries($input));
    }
}
