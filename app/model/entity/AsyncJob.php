<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use App\Exceptions\ApiException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;
use LogicException;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={
 *   @ORM\Index(name="created_at_idx", columns={"created_at"}),
 *   @ORM\Index(name="scheduled_at_idx", columns={"scheduled_at"}),
 *   @ORM\Index(name="started_at_idx", columns={"started_at"}),
 *   @ORM\Index(name="terminated_at_idx", columns={"terminated_at"})
 * })
 */
class AsyncJob implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @var User|null
     */
    protected $createdBy;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $createdAt;

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime
     * Time when the async task should be executed. If null, the task is executed as soon as possible.
     */
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
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime
     */
    protected $startedAt = null;

    public function getStartedAt(): ?DateTime
    {
        return $this->startedAt;
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime
     * This is the time when the task was either processed, killed, or deferred as unsolvable.
     */
    protected $terminatedAt = null;

    public function getTerminatedAt(): ?DateTime
    {
        return $this->terminatedAt;
    }

    public function setTerminatedNow()
    {
        $this->terminatedAt = new DateTime();
    }

    /**
     * @ORM\Column(type="integer")
     * @var int
     * Every time the task is taken for execution, this number is incremented.
     * A threshold of max. retries may be applied to avoid infinite re-execution of broken tasks.
     */
    protected $retries = 0;

    public function getRetries(): int
    {
        return $this->retries;
    }

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     * ID of a worker who took this task. Once a task is assigned to a worker, it may not be rellocated to another one.
     */
    protected $workerId = null;

    public function getWorkerId(): ?string
    {
        return $this->workerId;
    }

    /**
     * Allocate this job for patricular worker. If the job is allocated, the worker IDs must match.
     * The retry counter is incremented.
     * @param string $workerId
     * @throws LogicException
     */
    public function allocateForWorker(string $workerId)
    {
        if ($this->terminatedAt !== null) {
            throw new LogicException("Async job '$this->id' has already been terminated.");
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
     * @ORM\Column(type="string")
     * @var string
     */
    protected $command;

    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @ORM\Column(type="string")
     * @var string
     * Arguments of command encoded in json.
     */
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
     * @ORM\Column(type="string", nullable=true)
     * @var string|null
     * Last error message registered with this task.
     */
    protected $error = null;

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error)
    {
        $this->error = $error;
    }

    public function appendError(string $error)
    {
        if ($this->error === null) {
            $this->error = $error;
        } else {
            $this->error .= "\n$error";
        }
    }

    /**
     * @ORM\ManyToOne(targetEntity="Assignment", cascade={"persist", "remove"})
     * @var Assignment
     */
    protected $associatedAssignment = null;


    /**
     * Initialize a new job.
     * @param User $createdBy current user who initiated the job.
     * @param string $command class name of the handler that process the job
     * @param array $arguments for the job execution
     */
    public function __construct(?User $createdBy, string $command, array $arguments = [])
    {
        $this->createdBy = $createdBy;
        $this->createdAt = new DateTime();
        $this->command = $command;
        $this->arguments = json_encode($arguments);
    }

    public function jsonSerialize(): array
    {
        return [
            "id" => $this->id,
            "createdBy" => $this->createdBy->getId(),
            "createdAt" => $this->createdAt->getTimestamp(),
            "scheduledAt" => $this->scheduledAt->getTimestamp(),
            "startedAt" => $this->startedAt->getTimestamp(),
            "terminatedAt" => $this->terminatedAt->getTimestamp(),
            "retries" => $this->retries,
            "workerId" => $this->workerId,
            "command" => $this->command,
            "arguments" => $this->getArguments(),
            "error" => $this->error,
            "associatedAssignment" => $this->associatedAssignment,
        ];
    }
}
