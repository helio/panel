<?php

namespace Helio\Panel\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Panel\Model\User;
use Monolog\Logger;

class UserService
{
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
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws GuzzleException
     */
    public function create(string $email, bool $persistAndFlush = true): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setCreated();

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
}