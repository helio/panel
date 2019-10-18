<?php

namespace Helio\Panel\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\App;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\ServerUtility;
use Monolog\Logger;
use RuntimeException;

class UserService
{
    const BLENDER_MANAGER_NODE = 'manager-init-2rrahd.europe-west6-a.c.clusters-242906.internal';

    /**
     * @var EntityRepository
     */
    private $userRepository;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var ZapierHelper
     */
    private $zapierHelper;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(EntityRepository $userRepository, EntityManager $em, ZapierHelper $zapierHelper, Logger $logger)
    {
        $this->userRepository = $userRepository;
        $this->em = $em;
        $this->zapierHelper = $zapierHelper;
        $this->logger = $logger;
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this->userRepository->findOneBy(['email' => $email]);
    }

    public function findById(int $id): ?User
    {
        return $this->userRepository->find($id);
    }

    public function findAll(): array
    {
        return $this->userRepository->findAll();
    }

    /**
     * Creates a user, flushes the entity manager and sends a notification to zapier.
     *
     * Saving the demo user fails with a \InvalidArgumentException.
     *
     * @param string $email
     * @param string $origin
     * @param bool   $persistAndFlush
     *
     * @return User
     *
     * @throws GuzzleException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function create(string $email, string $origin, bool $persistAndFlush = true): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setOrigin($origin);
        $user->setCreated();

        if ($origin === ServerUtility::get('KOALA_FARM_ORIGIN')) {
            $prefs = $user->getPreferences();
            $limits = $prefs->getLimits();
            $limits->setJobTypes([JobType::BLENDER]);
            $limits->setManagerNodes([self::BLENDER_MANAGER_NODE]);
            $prefs->setLimits($limits);
            $user->setPreferences($prefs);
        }

        // TODO: ugly, but depends how it's used. Should find a better way.
        if ($persistAndFlush) {
            $this->em->persist($user);
            $this->em->flush();
        }

        if (!$this->zapierHelper->submitUserToZapier($user)) {
            $this->logger->warn('Unable to send notification to zapier for user ' . $user->getEmail());
            throw new \RuntimeException('Unable to send notification to zapier');
        }

        return $user;
    }

    public function login(string $email, string $origin): array
    {
        $user = $this->findUserByEmail($email);
        if (!$user) {
            $user = $this->create($email, $origin);
        }

        if (!App::getNotificationUtility()::sendConfirmationMail($user)) {
            throw new RuntimeException('Error during User Creation', 1545655919);
        }

        $token = null;

        return ['user' => $user, 'token' => $token];
    }
}
