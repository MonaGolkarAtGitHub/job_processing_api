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

    /**
     * Add new job.
     *
     * @param integer $submitterId
     * @param string $command
     * @param integer $priority
     * @return Job
     */
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

    /**
     * Save updated job.
     *
     * @param Job $job
     * @return Job
     */
    public function updateJob(Job $job): Job
    {
        $this->manager->persist($job);
        $this->manager->flush();
        $this->manager->refresh($job);

        return $job;
    }

    /**
     * Delete a job.
     *
     * @param Job $job
     */
    public function removeJob(Job $job)
    {
        $this->manager->remove($job);
        $this->manager->flush();
    }

    /**
     * Find one job by id or null if id not found.
     *
     * @param integer $id
     * @return Job|null
     */
    public function findOneJobById(int $id): ?Job
    {
        $queryBuilder = $this->_em->createQueryBuilder();
        $queryBuilder
            ->select('job')
            ->from($this->getEntityName(), 'job')
            ->where('job.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1);

        return $queryBuilder->getQuery()->useQueryCache(true)->useResultCache(true, 60)->getOneOrNullResult();
    }

    /**
     * Find jobs by processor id or null if id not found.
     *
     * @param integer $processorId
     * @return array|null
     */
    public function findJobsByProcessorId(int $processorId): ?array
    {
        $queryBuilder = $this->_em->createQueryBuilder();
        $queryBuilder
            ->select('job')
            ->from($this->getEntityName(), 'job')
            ->where('job.processorId = :processorId')
            ->andWhere('job.processEndDatatime IS NULL')
            ->setParameter('processorId', $processorId);

        return $queryBuilder->getQuery()->useQueryCache(true)->useResultCache(false)->getResult();
    }

    /**
     * Find the next waiting job in the priority queue.
     *
     * @return Job|null
     */
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

        return $queryBuilder->getQuery()->useQueryCache(true)->useResultCache(false)->getOneOrNullResult();
    }

    /**
     * Return average duration of completed jobs based on their priority.
     *
     * @return array|null
     */
    public function getAverageDurationPerPriority(): array
    {
        $sql = 'SELECT `priority`, SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(`process_end_datatime`,`creation_datetime`)))) AS timediff
                FROM `job`
                WHERE `processor_id` IS NOT NULL AND `process_end_datatime` IS NOT NULL
                GROUP BY `priority`
                ORDER BY `priority`';

        return $this->_em->getConnection()->prepare($sql)->execute()->fetchAll();
    }
}
