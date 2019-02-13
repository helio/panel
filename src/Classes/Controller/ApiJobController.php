<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Controller\Traits\AuthorizedJobController;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Master\MasterFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\MailUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApiController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/job')
 *
 */
class ApiJobController extends AbstractController
{
    use AuthorizedJobController;
    use TypeDynamicController;

    /**
     * @return ResponseInterface
     *
     * @Route("/remove", methods={"DELETE"}, name="job.remove")
     */
    public function removeJobAction(): ResponseInterface
    {
        $this->job->setHidden(true);
        $this->persistJob();
        return $this->render(['success' => true]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/add", methods={"POST"}, name="job.add")
     * @throws \Exception
     */
    public function addJobAction(): ResponseInterface
    {
        $this->requiredParameterCheck([
            'jobtype' => FILTER_SANITIZE_STRING
        ]);

        $this->optionalParameterCheck([
            'jobname' => FILTER_SANITIZE_STRING,
            'cpus' => FILTER_SANITIZE_STRING,
            'gpus' => FILTER_SANITIZE_STRING,
            'location' => FILTER_SANITIZE_STRING,
            'billingReference' => FILTER_SANITIZE_STRING,
            'budget' => FILTER_SANITIZE_STRING,
            'free' => FILTER_SANITIZE_STRING
        ]);

        if (!JobType::isValidType($this->params['jobtype'])) {
            return $this->render(['success' => false, 'message' => 'Unknown Job Type']);
        }

        $this->job->setName($this->params['jobname'] ?? 'Automatically named during creation')
            ->setStatus(JobStatus::READY)
            ->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()))
            ->setToken(JwtUtility::generateJobIdentificationToken($this->job))
            ->setType($this->params['jobtype'])
            ->setOwner($this->user)
            ->setCpus($this->params['cpus'] ?? '')
            ->setGpus($this->params['gpus'] ?? '')
            ->setLocation($this->params['location'] ?? '')
            ->setBillingReference($this->params['billingReference'] ?? '')
            ->setBudget($this->params['budget'] ?? '')
            ->setIsCharity($this->params['free'] ?? '' === 'on');

        JobFactory::getInstanceOfJob($this->job)->create($this->params);

        $this->persistJob();

        MailUtility::sendMailToAdmin('New Job was created by ' . $this->user->getEmail() . 'type: ' . $this->job->getType() . ', id: ' . $this->job->getId());


        // TODO: Remove this pseudo-instance as it's only for prototype purposes
        $demoInstanceName = 'Demo runner for prototype';
        $instance = $this->dbHelper->getRepository(Instance::class)->findOneBy(['name' => $demoInstanceName]);
        if (!$instance) {
            $owner = $this->dbHelper->getRepository(User::class)->findOneBy(['email' => 'team@opencomputing.cloud']);
            if (!$owner) {
                $owner = (new User())->setName('admin')->setEmail('team@opencomputing.cloud')->setAdmin(true)->setActive(true)->setCreated();
            }
            $instance = (new Instance())->setName($demoInstanceName)->setOwner($owner)->setStatus(InstanceStatus::RUNNING)->setCreated();
            $this->dbHelper->persist($instance);
        }
        $this->job->setDispatchedInstance($instance);
        $this->persistJob();

        MasterFactory::getMasterForInstance($instance)->dispatchJob($this->job);
        // TODO: End remove

        return $this->render([
            'success' => true,
            'token' => $this->job->getToken(),
            'id' => $this->job->getId(),
            'html' => $this->fetchPartial('listItemJob', ['job' => $this->job, 'user' => $this->user]),
            'message' => 'Job <strong>' . $this->job->getName() . '</strong> added'
        ]);
    }
}