<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;

/**
 * A record representing one processing batch of plagiarism detection.
 * The detection is performed by external tools (solutions are downloaded and results are uploaded via API).
 */
#[ORM\Entity]
class PlagiarismDetectionBatch implements JsonSerializable
{
    use CreatableEntity;

    /**
     * @var \Ramsey\Uuid\UuidInterface
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    /**
     * Identifier of the external tool that performed the detection.
     */
    #[ORM\Column(type: 'string')]
    protected $detectionTool;

    /**
     * Tool-specific parameters serialized into a string (e.g., CLI arguments use to invoke the tool)
     */
    #[ORM\Column(type: 'string')]
    protected $detectionToolParameters;

    /**
     * @var User
     * User responsible for the detection process.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $supervisor;

    /**
     * @var DateTime
     * Time when all the plagiarism records were uploaded. If null, the upload is still pending.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
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
        ?User $supervisor = null
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

    public function getDetectionToolParameters(): string
    {
        return $this->detectionToolParameters;
    }

    public function getUploadCompletedAt(): ?DateTime
    {
        return $this->uploadCompletedAt;
    }
}
