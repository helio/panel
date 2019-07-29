<?php

namespace Helio\Panel\Utility;

use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Psr\Http\Message\ResponseInterface;

class ExecUtility extends AbstractUtility
{


    /**
     * @param string $endpoint
     * @param Task|null $task
     * @param Job|null $job
     * @return string
     */
    public static function getExecUrl(string $endpoint = '', Task $task = null, Job $job = null): string
    {
        if ($endpoint && strpos($endpoint, '/') !== 0) {
            $endpoint = '/' . $endpoint;
        }
        return "api/exec$endpoint" . ($job || $task ? '?' : '') . ($job ? 'jobid=' . $job->getId() : '') . ($job && $task ? '&' : '') . ($task ? 'taskid=' . $task->getId() : '');
    }


    /**
     * @param Task $task
     * @return string
     */
    public static function getTaskDataFolder(Task $task): string
    {
        $folder = self::getJobDataFolder($task->getJob()) . $task->getId() . DIRECTORY_SEPARATOR;
        if (!\is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $folder));
        }
        return $folder;
    }


    /**
     * @param Job $job
     * @return string
     */
    public static function getJobDataFolder(Job $job): string
    {
        $folder = ServerUtility::getTmpPath() . DIRECTORY_SEPARATOR . 'jobdata' . DIRECTORY_SEPARATOR . $job->getId() . DIRECTORY_SEPARATOR;
        if (!\is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $folder));
        }
        return $folder;
    }


    /**
     * @param string $file
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public static function downloadFile(string $file, ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withoutHeader('Content-Description')->withHeader('Content-Description', 'File Transfer')
            ->withoutHeader('Content-Type')->withHeader('Content-Type', 'application/octet-stream')
            ->withoutHeader('Content-Disposition')->withHeader('Content-Disposition', 'attachment; filename="' . basename($file) . '"')
            ->withoutHeader('Expires')->withHeader('Expires', '0')
            ->withoutHeader('Cache-Control')->withHeader('Cache-Control', 'must-revalidate')
            ->withoutHeader('Pragma')->withHeader('Pragma', 'public')
            ->withoutHeader('Content-Length')->withHeader('Content-Length', filesize($file))
            ->withBody(new \GuzzleHttp\Psr7\LazyOpenStream($file, 'r'));
    }
}