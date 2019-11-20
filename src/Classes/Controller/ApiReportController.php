<?php

namespace Helio\Panel\Controller;

use Helio\Panel\App;
use Helio\Panel\Controller\Report\BlenderReport;
use Helio\Panel\Controller\Traits\AuthorizedAdminController;
use Helio\Panel\Exception\HttpException;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Repositories\JobRepository;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * @RoutePrefix('/api/report')
 */
class ApiReportController extends AbstractController
{
    use AuthorizedAdminController;

    /**
     * @Route("/job/{id:[\d]+}/summary", methods={"GET"}, name="job.summary.report")
     */
    public function jobReportAction(int $id): ResponseInterface
    {
        /** @var JobRepository $repository */
        $repository = App::getDbHelper()->getRepository(Job::class);
        /** @var Job $job */
        $job = $repository->find($id);
        if (!$job) {
            throw new HttpException(StatusCode::HTTP_NOT_FOUND, 'job not found');
        }

        $executions = $job->getExecutions()->map(
            function (Execution $execution) {
                $stats = $execution->getStats();

                return [
                    'config' => json_decode($execution->getConfig()),
                    'result' => strlen($stats) > 0 ? json_decode($stats) : null,
                ];
            }
        );

        return $this->render([
            'config' => json_decode($job->getConfig()),
            'executions' => $executions->isEmpty() ? null : $executions->toArray(),
        ]);
    }

    /**
     * @Route("/type/blender", methods={"GET"}, name="blender.report")
     */
    public function blenderReportAction(): ResponseInterface
    {
        $limit = (int) $this->request->getQueryParam('limit', '100');
        $offset = (int) $this->request->getQueryParam('offset', '0');

        /** @var JobRepository $repository */
        $repository = App::getDbHelper()->getRepository(Job::class);

        $blenderReport = new BlenderReport($repository);
        $data = $blenderReport->generate($limit, $offset);

        $bodyString = implode("\n", array_map(function (array $row) {
            return implode(',', $row);
        }, $data));

        // I *love* mutability, says the framework author :(
        $this->response
            ->getBody()
            ->write($bodyString);

        return $this->response
            ->withStatus(StatusCode::HTTP_OK)
            ->withHeader('Content-Type', 'text/plain');
    }

    protected function getReturnType(): string
    {
        return 'json';
    }

    protected function getMode(): string
    {
        return 'api';
    }
}
