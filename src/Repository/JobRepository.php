<?php

namespace App\Repository;

use App\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method Job|null find($id, $lockMode = null, $lockVersion = null)
 * @method Job|null findOneBy(array $criteria, array $orderBy = null)
 * @method Job[]    findAll()
 * @method Job[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JobRepository extends ServiceEntityRepository
{
    private $manager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $manager)
    {
        parent::__construct($registry, Job::class);
        $this->manager = $manager;
    }

    public function addJob(int $submitterId, string $command, int $priority): Job
    {
        $job = new Job();

        $job->setSubmitterId($submitterId)
            ->setCommand($command)
            ->setPriority($priority);

        $this->manager->persist($job);
        $this->manager->flush();
        $this->manager->refresh($job);

        return $job;
    }

    public function updateJob(Job $job): Job
    {
        $this->manager->persist($job);
        $this->manager->flush();
        $this->manager->refresh($job);

        return $job;
    }

    public function removeJob(Job $job)
    {
        $this->manager->remove($job);
        $this->manager->flush();
    }

    public function findNextJobToProcess(): ?Job
    {
        $queryBuilder = $this->_em->createQueryBuilder();
        $queryBuilder
            ->select('job')
            ->from($this->getEntityName(), 'job')
            ->where('job.processorId IS NULL')
            ->andWhere('job.processEndDatatime IS NULL')
            ->add('orderBy','job.priority DESC, job.id ASC')
            ->setMaxResults(1);

        return $queryBuilder->getQuery()->useQueryCache(true)->useResultCache(true, 60)->getOneOrNullResult();
    }
}
