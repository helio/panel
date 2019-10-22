<?php

namespace Helio\Test\Unit;

use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Utility\ExecUtility;
use Helio\Test\TestCase;

class ExecUtilityTest extends TestCase
{
    /**
     * @dataProvider getExecUrlDataProvider
     */
    public function testGetExecUrl(array $input, string $expected): void
    {
        ['jobID' => $jobID, 'endpoint' => $endpoint] = $input;
        $executionID = null;
        if (isset($input['executionID'])) {
            $executionID = $input['executionID'];
        }

        $job = (new Job())->setId($jobID);
        $execution = null;
        if ($executionID) {
            $execution = (new Execution())->setId($executionID);
        }

        $this->assertEquals($expected, ExecUtility::getExecUrl($job, $endpoint, $execution));
    }

    public function getExecUrlDataProvider()
    {
        return [
            [
                [
                    'jobID' => 1,
                    'executionID' => 11,
                    'endpoint' => '',
                ],
                'api/job/1/execute?id=11',
            ],
            [
                [
                    'jobID' => 2,
                    'executionID' => 22,
                    'endpoint' => 'submitresult',
                ],
                'api/job/2/execute/submitresult?id=22',
            ],
            [
                [
                    'jobID' => 3,
                    'endpoint' => 'submitresult',
                ],
                'api/job/3/execute/submitresult',
            ],
            [
                [
                    'jobID' => 4,
                    'executionID' => 44,
                    'endpoint' => '/submitresult',
                ],
                'api/job/4/execute/submitresult?id=44',
            ],
        ];
    }
}
