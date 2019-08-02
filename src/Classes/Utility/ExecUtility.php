<?php

namespace Helio\Panel\Utility;

use \RuntimeException;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Psr\Http\Message\ResponseInterface;
use \GuzzleHttp\Psr7\LazyOpenStream;

/**
 * Class ExecUtility
 * @package Helio\Panel\Utility
 */
class ExecUtility extends AbstractUtility
{


    /**
     * @param Job $job
     * @param string $endpoint
     * @param Execution|null $execution
     * @return string
     */
    public static function getExecUrl(Job $job, string $endpoint = '', Execution $execution = null): string
    {
        if ($endpoint && strpos($endpoint, '/') !== 0) {
            $endpoint = '/' . $endpoint;
        }
        return "api/job/" . $job->getId() . "/execute$endpoint" . ($execution ? '?id=' . $execution->getId() : '');
    }


    /**
     * @param Execution $execution
     * @return string
     */
    public static function getExecutionDataFolder(Execution $execution): string
    {
        $folder = self::getJobDataFolder($execution->getJob()) . $execution->getId() . DIRECTORY_SEPARATOR;
        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $folder));
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
        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $folder));
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
            ->withoutHeader('Content - Description')->withHeader('Content - Description', 'File Transfer')
            ->withoutHeader('Content - Type')->withHeader('Content - Type', 'application / octet - stream')
            ->withoutHeader('Content - Disposition')->withHeader('Content - Disposition', 'attachment; filename = "' . basename($file) . '"')
            ->withoutHeader('Expires')->withHeader('Expires', '0')
            ->withoutHeader('Cache - Control')->withHeader('Cache - Control', 'must - revalidate')
            ->withoutHeader('Pragma')->withHeader('Pragma', 'public')
            ->withoutHeader('Content - Length')->withHeader('Content - Length', filesize($file))
            ->withBody(new LazyOpenStream($file, 'r'));
    }
}