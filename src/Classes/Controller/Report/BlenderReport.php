<?php

namespace Helio\Panel\Controller\Report;

use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Repositories\JobRepository;

class BlenderReport
{
    /**
     * @var JobRepository
     */
    private $repository;

    public function __construct(JobRepository $repository)
    {
        $this->repository = $repository;
    }

    public function generate(int $limit = 100, int $offset = 0): array
    {
        /** @var Job[] $jobs */
        $jobs = $this->repository->findBy(['type' => 'blender', 'status' => [JobStatus::READY, JobStatus::DONE, JobStatus::DELETING, JobStatus::DELETED]], ['created' => 'ASC'], $limit, $offset);

        $combinedJobs = [];
        foreach ($jobs as $job) {
            $type = $job->getConfig('type');
            if ('render' !== $type && 'estimation' !== $type) {
                continue;
            }
            $combinedJobs[$job->getName()] = $this->generateJobData($job, $combinedJobs[$job->getName()] ?? []);
        }

        $combinedJobs = array_filter($combinedJobs, function (array $data) {
            return false !== strpos($data['id'], '-') && $data['serial_reported_duration_ms'] > 0;
        });

        $rows = array_map(function (array $data) {
            return [
                $data['id'],
                $data['name'],
                $data['estimation_resolution_percentage'],
                $data['target_resolution_percentage'],
                $data['rendered_frames_count'],
                $data['estimated_duration_ms'],
                $data['serial_reported_duration_ms'],
                $data['project_duration_s'],
                false === $data['success'] ? 'false' : 'true',
            ];
        }, $combinedJobs);

        $report = array_merge([
            ['id', 'name', 'estimation_resolution_percentage', 'target_resolution_percentage', 'rendered_frames_count', 'estimated_duration_ms', 'serial_reported_duration_ms', 'project_duration_s', 'success'],
        ], $rows);

        return $report;
    }

    protected function generateJobData(Job $job, array $jobData): array
    {
        $config = json_decode($job->getConfig(), true);
        $type = $config['type'];
        $settings = $config['settings'];
        $resolutionPercentage = $settings['resolutionPercentage'];

        [$success, $lastHeartbeat, $executions] = $this->getJobExecutionsData($job);

        if ('render' === $type) {
            $jobData['id'] .= '-' . $job->getId();
            $jobData['target_resolution_percentage'] = $resolutionPercentage;
            $serialReportedDuration = 0;

            foreach ($executions as $execution) {
                $serialReportedDuration += $execution['duration_ms'];
            }

            $jobData['rendered_frames_count'] = $config['settings']['frames']['end'] - $config['settings']['frames']['start'] + 1;
            $jobData['estimated_duration_ms'] = $config['estimation']['duration'];
            $jobData['serial_reported_duration_ms'] = $serialReportedDuration;
            $jobData['project_duration_s'] = $lastHeartbeat->getTimestamp() - $job->getCreated()->getTimestamp();
        } else {
            $jobData['id'] = $job->getId();
            $jobData['name'] = $job->getName();
            $jobData['estimation_resolution_percentage'] = $resolutionPercentage;
        }

        if (null === $jobData['success'] && false === $success) {
            $jobData['success'] = $success;
        }

        return $jobData;
    }

    protected function getJobExecutionsData(Job $job): array
    {
        $success = true;
        $lastHeartbeat = new \DateTime('1970-01-01');

        $executions = $job->getStartedExecutions()->map(
            function (Execution $execution) use (&$success, &$lastHeartbeat) {
                $result = json_decode($execution->getStats(), true);
                $heartbeat = $execution->getLatestHeartbeat();
                if ($heartbeat > $lastHeartbeat) {
                    $lastHeartbeat = $heartbeat;
                }

                if (false === $result['success'] && null === $result['duration_ms']) {
                    $success = false;
                }

                return [
                    'duration_ms' => $result['duration_ms'] ?? 0,
                ];
            }
        )->toArray();

        return [$success, $lastHeartbeat, $executions];
    }
}
