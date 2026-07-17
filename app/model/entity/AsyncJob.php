<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use JsonSerializable;

#[ORM\Table]
#[ORM\Index(name: 'created_at_idx', columns: ['created_at'])]
#[ORM\Index(name: 'scheduled_at_idx', columns: ['scheduled_at'])]
#[ORM\Index(name: 'started_at_idx', columns: ['started_at'])]
#[ORM\Index(name: 'finished_at_idx', columns: ['finished_at'])]
#[ORM\Entity]
class AsyncJob implements JsonSerializable
{
    /**
     * @var \Ramsey\Uuid\UuidInterface
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    /**
     * @var User|null
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $createdBy;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * @var DateTime
     */
    #[ORM\Column(type: 'datetime')]
    protected $createdAt;

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @var DateTime|null
     * Time when the async task should be executed. If null, the task is executed as soon as possible.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    protected $scheduledAt = null;

    public function getScheduledAt(): ?DateTime
    {
        return $this->scheduledAt;
    }

    /**
     * Sets the time in the future when this async task should be executed.
     * @throws InvalidArgumentException
     */
    public function setScheduledAt(DateTime $scheduledAt)
    {
        if ($scheduledAt < new DateTime()) {
            throw new InvalidArgumentException("Scheduled time may not be in the past.");
        }
        $this->scheduledAt = $scheduledAt;
    }

    /**
     * @var DateTime|null
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    protected $startedAt = null;

    public function getStartedAt(): ?DateTime
    {
        return $this->startedAt;
    }

    /**
     * @var DateTime|null
     * This is the time when the task was either processed, killed, or deferred as unsolvable.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    protected $finishedAt = null;

    public function getFinishedAt(): ?DateTime
    {
        return $this->finishedAt;
    }

    public function setFinishedNow()
    {
        $this->finishedAt = new DateTime();
    }

    /**
     * @var int
     * Every time the task is taken for execution, this number is incremented.
     * A threshold of max. retries may be applied to avoid infinite re-execution of broken tasks.
     */
    #[ORM\Column(type: 'integer')]
    protected $retries = 0;

    public function getRetries(): int
    {
        return $this->retries;
    }

    /**
     * @var string
     * ID of a worker who took this task. Once a task is assigned to a worker, it may not be relocated to another one.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    protected $workerId = null;

    public function getWorkerId(): ?string
    {
        return $this->workerId;
    }

    /**
     * Allocate this job for particular worker. If the job is allocated, the worker IDs must match.
     * The retry counter is incremented.
     * @param string $workerId
     * @throws LogicException
     */
    public function allocateForWorker(string $workerId)
    {
        if ($this->finishedAt !== null) {
            throw new LogicException("Async job '$this->id' has already finished.");
        }

        if ($this->workerId !== null && $this->workerId !== $workerId) {
            throw new LogicException("Async job '$this->id' has already been allocated for worker '$this->workerId'. "
                . "Unable to (re)allocate for worker '$workerId'.");
        }

        if ($this->startedAt === null) {
            $this->startedAt = new DateTime();
        }
        $this->workerId = $workerId;
        ++$this->retries;
    }

    /**
     * @var string
     */
    #[ORM\Column(type: 'string')]
    protected $command;

    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @var string
     * Arguments of command encoded in json.
     */
    #[ORM\Column(type: 'string')]
    protected $arguments = '';

    /**
     * Get parsed arguments (collections remain objects, not assoc arrays).
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments ? json_decode($this->arguments, false) : [];
    }

    /**
     * Set the async command and its arguments.
     * @param string $command
     * @param array $arguments
     */
    public function setCommand(string $command, array $arguments = [])
    {
        $this->command = $command;
        $this->arguments = $arguments ? json_encode($arguments) : '';
    }

    /**
     * @var string|null
     * Last error message registered with this task.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    protected $error = null;

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Makes sure the error string fits the data column.
     * Truncates and adds ellipsis '...' at the end if it overflows.
     */
    private function sanitizeErrorLength(): void
    {
        if ($this->error !== null && strlen($this->error) > 250) {
            $this->error = substr($this->error, 0, 250) . '...';
        }
    }

    public function setError(?string $error)
    {
        $this->error = $error;
        $this->sanitizeErrorLength();
    }

    public function appendError(string $error)
    {
        if ($this->error === null) {
            $this->error = $error;
        } else {
            $this->error .= "\n$error";
        }
        $this->sanitizeErrorLength();
    }

    /**
     * @var Assignment|null
     */
    #[ORM\ManyToOne(targetEntity: Assignment::class, cascade: ['persist'])]
    protected $associatedAssignment = null;

    public function getAssociatedAssignment(): ?Assignment
    {
        return $this->associatedAssignment;
    }


    /**
     * Initialize a new job.
     * @param User $createdBy current user who initiated the job.
     * @param string $command class name of the handler that process the job
     * @param array $arguments for the job execution
     * @param Assignment|null $assignment associated with the job
     */
    public function __construct(
        ?User $createdBy,
        string $command,
        array $arguments = [],
        ?Assignment $assignment = null
    ) {
        $this->createdBy = $createdBy;
        $this->createdAt = new DateTime();
        $this->command = $command;
        $this->arguments = json_encode($arguments);
        $this->associatedAssignment = $assignment;
    }

    public function jsonSerialize(): array
    {
        return [
            "id" => $this->getId(),
            "createdBy" => $this->createdBy ? $this->createdBy->getId() : null,
            "createdAt" => $this->createdAt->getTimestamp(),
            "scheduledAt" => $this->scheduledAt ? $this->scheduledAt->getTimestamp() : null,
            "startedAt" => $this->startedAt ? $this->startedAt->getTimestamp() : null,
            "finishedAt" => $this->finishedAt ? $this->finishedAt->getTimestamp() : null,
            "retries" => $this->retries,
            "workerId" => $this->workerId,
            "command" => $this->command,
            "arguments" => $this->getArguments(),
            "error" => $this->error,
            "associatedAssignment" => $this->associatedAssignment ? $this->associatedAssignment->getId() : null,
        ];
    }
}
