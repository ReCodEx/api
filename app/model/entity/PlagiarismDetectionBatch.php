<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * A record representing one processing batch of plagiarism detection.
 * The detection is performed by external tools (solutions are downloaded and results are uploaded via API).
 */
class PlagiarismDetectionBatch implements JsonSerializable
{
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     * Identifier of the external tool that performed the detection.
     */
    protected $detectionTool;

    /**
     * @ORM\Column(type="string")
     * Tool-specific parameters serialized into a string (e.g., CLI arguments use to invoke the tool)
     */
    protected $detectionToolParameters;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="User")
     * User responsible for the detection process.
     */
    protected $supervisor;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", nullable=true)
     * Time when all the plagiate records were uploaded. If null, the upload is still pending.
     */
    protected $uploadCompletedAt = null;

    /**
     * Mark the batch upload as completed (set actual date time).
     */
    public function setUploadCompleted(bool $completed = true): void
    {
        $this->uploadCompletedAt = $completed ? new DateTime() : null;
    }

    /**
     * Detection batch constructor.
     * @param string $detectionTool
     * @param string $toolParameters
     * @param User|null $supervisor
     */
    public function __construct(
        string $detectionTool,
        string $toolParameters,
        User $supervisor = null
    ) {
        $this->detectionTool = $detectionTool;
        $this->detectionToolParameters = $toolParameters;
        $this->supervisor = $supervisor;
        $this->createdAt = new DateTime();
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "detectionTool" => $this->getDetectionTool(),
            "supervisorId" => $this->getSupervisor() ? $this->getSupervisor()->getId() : null,
            "createdAt" => $this->createdAt->getTimestamp(),
            "uploadCompletedAt" => $this->getUploadCompletedAt() ? $this->getUploadCompletedAt()->getTimestamp() : null,
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getSupervisor(): ?User
    {
        return $this->supervisor === null || $this->supervisor->isDeleted() ? null : $this->supervisor;
    }

    public function getDetectionTool(): string
    {
        return $this->detectionTool;
    }

    public function getUploadCompletedAt(): ?DateTime
    {
        return $this->uploadCompletedAt;
    }
}
