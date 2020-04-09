<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\JobRepository")
 */
class Job
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="bigint")
     */
    private $id;

    /**
     * @Assert\NotBlank(message="Submitter id is mandatory")
     * @ORM\Column(type="bigint")
     */
    private $submitterId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $processorId;

    /**
     * @Assert\NotBlank
     * @ORM\Column(type="string", length=255)
     */
    private $command;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $priority;

    /**
     * @Assert\DateTime()
     * @ORM\Column(type="datetime")
     */
    private $creationDatetime;

    /**
     * @Assert\DateTime()
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $processEndDatatime;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime("now");
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSubmitterId(): int
    {
        return $this->submitterId;
    }

    public function setSubmitterId(int $submitterId): self
    {
        $this->submitterId = $submitterId;

        return $this;
    }

    public function getProcessorId(): ?int
    {
        return $this->processorId;
    }

    public function setProcessorId(?int $processorId): self
    {
        $this->processorId = $processorId;

        return $this;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function setCommand(string $command): self
    {
        $this->command = $command;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getCreationDatetime(): ?\DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getProcessEndDatatime(): ?\DateTimeInterface
    {
        return $this->processEndDatatime;
    }

    public function setProcessEndDatatime(): self
    {
        $this->processEndDatatime = new \DateTime("now");

        return $this;
    }

    /**
     * Get array for serialization.
     *
     * @return array
     */
    public function getArray()
    {
        return [
            'id' => $this->getId(),
            'submitter_id' => $this->getSubmitterId(),
            'processor_id' => $this->getProcessorId(),
            'command' => $this->getCommand(),
            'priority' => $this->getPriority(),
            'creation_datetime' => $this->getCreationDatetime(),
            'process_end_datetime' => $this->getProcessEndDatatime()
        ];
    }
}
