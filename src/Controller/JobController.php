<?php

namespace App\Controller;

use App\Util\CacheInterface;
use App\Repository\JobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

class JobController extends AbstractController
{
    private $cachePool;
    private $cacheUtil;
    private $jobRepository;

    const PRIORITY_SET = [
        'high'      => 2,
        'normal'    => 1,
        'low'       => 0
    ];

    public function __construct(JobRepository $jobRepository, MemcachedAdapter $cachePool, CacheInterface $cacheUtil)
    {
        $this->jobRepository = $jobRepository;
        $this->cachePool = $cachePool;
        $this->cacheUtil = $cacheUtil;
    }

    /**
     * Add a new job.
     *
     * It will receive parameters through json and will attempt to create a new job.
     *
     * @Route("/job", name="add_new_job", methods={"POST"})
     *
     * @SWG\Parameter(
     *     name="submitter_id",
     *     in="body",
     *     type="integer",
     *     description="The identifier of submitter",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="command",
     *     in="body",
     *     type="string",
     *     description="The command included in the job",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="priority",
     *     in="body",
     *     type="string",
     *     description="Priority of job",
     *     required=true,
     *     enum={"low", "normal", "high"}
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the id of the added job",
     *     @SWG\Schema(
     *         type="json"
     *     )
     * )
     * @SWG\Response(
     *     response=400,
     *     description="Returned when one or more parameters are missing or invalid"
     * )
     *
     */
    public function addJob(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $submitterId = $data['submitter_id'];
        $command = $data['command'];
        $priority = $data['priority'];

        if (empty($submitterId) || empty($command) || empty($priority)) {
            return new JsonResponse('Missing mandatory parameters', Response::HTTP_BAD_REQUEST);
        }

        if (intval($submitterId) === 0 || array_key_exists($priority, array_keys(self::PRIORITY_SET))) {
            return new JsonResponse('Invalid mandatory parameters', Response::HTTP_BAD_REQUEST);
        }

        $newJob = $this->jobRepository->addJob(intval($submitterId), $command, self::PRIORITY_SET[$priority]);
        $this->cacheUtil->saveItem($this->cachePool, strval($newJob->getId()), serialize($newJob->getArray()));

        return new JsonResponse(['id' => $newJob->getId()], Response::HTTP_OK);
    }

    /**
     * Get the job status.
     *
     * It will return the status of the job.
     *
     * @Route("/job/{id}", name="get_job_status", methods={"GET","HEAD"})
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the status of the job",
     *     @SWG\Schema(
     *         type="json"
     *     )
     * )
     * @SWG\Response(
     *     response=404,
     *     description="Returned when the job could not be found"
     * )
     *
     */
    public function getJobStatus(int $id): JsonResponse
    {
        $jobCachedData = $this->cacheUtil->getItem($this->cachePool, strval($id));

        if (!empty($jobCachedData)) {
            return new JsonResponse(['status' => self::getJobStatusName(unserialize($jobCachedData))], Response::HTTP_OK);
        }

        $job = $this->jobRepository->findOneJobById($id);
        if (empty($job)) {
            return new JsonResponse('Data not found', Response::HTTP_NOT_FOUND);
        }

        $jobStatus = self::getJobStatusName($job->getArray());
        if ($jobStatus !== 'completed') {
            $this->cacheUtil->saveItem($this->cachePool, strval($job->getId()), serialize($job->getArray()));
        }

        return new JsonResponse(['status' => $jobStatus], Response::HTTP_OK);
    }

    /**
     * Get the next job's information.
     *
     * It will return the next job in queue based on their priority.
     *
     * @Route("/job/", name="get_job_to_process", methods={"GET","HEAD"})
     *
     * @SWG\Parameter(
     *     name="processor_id",
     *     in="header",
     *     type="integer",
     *     description="The identifier of processor",
     *     required=true
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns id and command associated to the next job in the priority queue",
     *     @SWG\Schema(
     *         type="json"
     *     )
     * )
     * @SWG\Response(
     *     response=400,
     *     description="Returned when the parameter is missing or invalid"
     * )
     *
     */
    public function getJobToProcess(Request $request): JsonResponse
    {
        $headers = $request->headers;
        if (!in_array('processor-id', $headers->keys()) || empty($headers->get('processor-id'))) {
            return new JsonResponse('Missing mandatory header parameter', Response::HTTP_BAD_REQUEST);
        }

        if (!empty($this->jobRepository->findJobsByProcessorId(intval($headers->get('processor-id'))))) {

            return new JsonResponse('Processor is still processing another job', Response::HTTP_BAD_REQUEST);
        }

        $job = $this->jobRepository->findNextJobToProcess();
        if (empty($job)) {
            return new JsonResponse('No job found', Response::HTTP_OK);
        }

        $job->setProcessorId(intval($headers->get('processor-id')));
        $job = $this->jobRepository->updateJob($job);
        $this->cacheUtil->saveItem($this->cachePool, strval($job->getId()), serialize($job->getArray()));

        return new JsonResponse(['id' => $job->getId(), 'command' => $job->getCommand()], Response::HTTP_OK);
    }

    /**
     * Update job as finished.
     *
     * It will update the job to set it as finished by setting the process end timestamp.
     *
     * @Route("/job/", methods={"PUT"})
     *
     * @SWG\Parameter(
     *     name="id",
     *     in="body",
     *     type="integer",
     *     description="The identifier of the job that has finished",
     *     required=true
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the when the job is updated successfully",
     * )
     * @SWG\Response(
     *     response=400,
     *     description="Returned when the parameter is missing or invalid"
     * )
     * @SWG\Response(
     *     response=404,
     *     description="Returned when the job could not be found"
     * )
     *
     */
    public function updateFinishedJob(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data['id'])) {
            return new JsonResponse('Missing mandatory parameters', Response::HTTP_BAD_REQUEST);
        }

        $job = $this->jobRepository->findOneJobById($data['id']);
        if (empty($job)) {
            return new JsonResponse('Data not found', Response::HTTP_NOT_FOUND);
        }

        if (self::getJobStatusName($job->getArray()) !== 'processing') {
            return new JsonResponse('Invalid request', Response::HTTP_BAD_REQUEST);
        }

        $job->setProcessEndDatatime();
        $job = $this->jobRepository->updateJob($job);
        $this->cacheUtil->saveItem($this->cachePool, strval($job->getId()), serialize($job->getArray()));

        return new JsonResponse('Data updated', Response::HTTP_OK);
    }

    private static function getJobStatusName(array $jobData)
    {
        return !empty($jobData["processor_id"]) ? !empty($jobData["process_end_datetime"]) ? "completed" : "processing" : "waiting";
    }
}
