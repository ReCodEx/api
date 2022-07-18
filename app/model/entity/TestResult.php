<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Helpers\EvaluationResults as ER;
use App\Model\View\Helpers\SubmissionViewOptions;

/**
 * @ORM\Entity
 */
class TestResult
{
    public function __construct(
        SolutionEvaluation $evaluation,
        ER\TestResult $result
    ) {
        $this->solutionEvaluation = $evaluation;
        $this->testName = $result->getId();
        $this->status = $result->getStatus();
        $this->score = $result->getScore();
        $this->exitCode = $result->getExitCode();
        $this->exitSignal = $result->getExitSignal();
        $this->usedMemory = $result->getUsedMemory();
        $this->usedMemoryLimit = $result->getUsedMemoryLimit();
        $this->memoryExceeded = !$result->isMemoryOK();
        $this->usedWallTime = $result->getUsedWallTime();
        $this->usedWallTimeLimit = $result->getUsedWallTimeLimit();
        $this->wallTimeExceeded = !$result->isWallTimeOK();
        $this->usedCpuTime = $result->getUsedCpuTime();
        $this->usedCpuTimeLimit = $result->getUsedCpuTimeLimit();
        $this->cpuTimeExceeded = !$result->isCpuTimeOK();
        $this->message = substr($result->getMessage(), 0, 255);  // maximal size of varchar

        // log sizes are limited by the size of the text column
        $this->judgeStdout = substr($result->getJudgeStdout(), 0, 65536);
        $this->judgeStderr = substr($result->getJudgeStderr(), 0, 65536);
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $testName;

    /**
     * @ORM\Column(type="string")
     */
    protected $status;

    /**
     * @ORM\ManyToOne(targetEntity="SolutionEvaluation", inversedBy="testResults")
     */
    protected $solutionEvaluation;

    /**
     * @ORM\Column(type="float")
     */
    protected $score;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $memoryExceeded;

    /**
     * @ORM\Column(type="integer")
     */
    protected $usedMemory;

    /**
     * @ORM\Column(type="integer")
     */
    protected $usedMemoryLimit;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $wallTimeExceeded;

    /**
     * @ORM\Column(type="float")
     */
    protected $usedWallTime;

    /**
     * @ORM\Column(type="float")
     */
    protected $usedWallTimeLimit;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $cpuTimeExceeded;

    /**
     * @ORM\Column(type="float")
     */
    protected $usedCpuTime;

    /**
     * @ORM\Column(type="float")
     */
    protected $usedCpuTimeLimit;

    /**
     * @ORM\Column(type="integer")
     */
    protected $exitCode;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $exitSignal;

    /**
     * @ORM\Column(type="string")
     */
    protected $message;

    /**
     * @ORM\Column(type="text", length=65535, nullable=true)
     */
    protected $judgeStdout;

    /**
     * @ORM\Column(type="text", length=65535, nullable=true)
     */
    protected $judgeStderr;

    public function getDataForView(SubmissionViewOptions $options)
    {
        $wallTime = null;
        $wallTimeRatio = null;
        $wallTimeLimit = null;
        $cpuTime = null;
        $cpuTimeRatio = null;
        $cpuTimeLimit = null;
        $memory = null;
        $memoryRatio = null;
        $memoryLimit = null;
        $judgeLogStdout = null;
        $judgeLogStderr = null;

        if ($options->canViewDetails()) {
            $wallTimeLimit = $this->usedWallTimeLimit;
            $cpuTimeLimit = $this->usedCpuTimeLimit;
            $memoryLimit = $this->usedMemoryLimit;

            $wallTimeRatio = $this->usedWallTimeLimit == 0 ? 0.0 :
                floatval($this->usedWallTime) / floatval($this->usedWallTimeLimit);
            $cpuTimeRatio = $this->usedCpuTimeLimit == 0 ? 0.0 :
                floatval($this->usedCpuTime) / floatval($this->usedCpuTimeLimit);
            $memoryRatio = $this->usedMemoryLimit == 0 ? 0.0 :
                floatval($this->usedMemory) / floatval($this->usedMemoryLimit);
        }
        if ($options->canViewValues()) {
            $wallTime = $this->usedWallTime;
            $cpuTime = $this->usedCpuTime;
            $memory = $this->usedMemory;
        }
        if ($options->canViewJudgeStdout()) {
            $judgeLogStdout = $this->judgeStdout;
            if ($options->mergeJudgeLogs() && $this->judgeStderr) {
                // append stderr right after stdout
                $judgeLogStdout .= ($judgeLogStdout ? "\n" : '') . $this->judgeStderr;
            }
        }
        if ($options->canViewJudgeStderr() && !$options->mergeJudgeLogs()) {
            $judgeLogStderr = $this->judgeStderr;
        }

        return [
            "id" => $this->getId(),
            "testName" => $this->testName,
            "solutionEvaluationId" => $this->solutionEvaluation->getId(),
            "status" => $this->status,
            "score" => $this->score,
            "memoryExceeded" => $this->memoryExceeded,
            "wallTimeExceeded" => $this->wallTimeExceeded,
            "cpuTimeExceeded" => $this->cpuTimeExceeded,
            "exitCode" => $this->exitCode,
            "exitSignal" => $this->exitSignal,
            "message" => $this->message,
            "wallTimeRatio" => $wallTimeRatio,
            "cpuTimeRatio" => $cpuTimeRatio,
            "memoryRatio" => $memoryRatio,
            "wallTime" => $wallTime,
            "cpuTime" => $cpuTime,
            "memory" => $memory,
            "wallTimeLimit" => $wallTimeLimit,
            "cpuTimeLimit" => $cpuTimeLimit,
            "memoryLimit" => $memoryLimit,
            "judgeLogStdout" => $judgeLogStdout,
            "judgeLogStderr" => $judgeLogStderr,
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTestName(): string
    {
        return $this->testName;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getUsedMemory(): int
    {
        return $this->usedMemory;
    }

    public function getUsedMemoryLimit(): int
    {
        return $this->usedMemoryLimit;
    }

    public function getUsedWallTime(): float
    {
        return $this->usedWallTime;
    }

    public function getUsedWallTimeLimit(): float
    {
        return $this->usedWallTimeLimit;
    }

    public function getUsedCpuTime(): float
    {
        return $this->usedCpuTime;
    }

    public function getUsedCpuTimeLimit(): float
    {
        return $this->usedCpuTimeLimit;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getExitSignal(): ?int
    {
        return $this->exitSignal;
    }

    public function getCpuTimeExceeded(): bool
    {
        return $this->cpuTimeExceeded;
    }

    public function getWallTimeExceeded(): bool
    {
        return $this->wallTimeExceeded;
    }

    public function getMemoryExceeded(): bool
    {
        return $this->memoryExceeded;
    }

    public function getSolutionEvaluation(): SolutionEvaluation
    {
        return $this->solutionEvaluation;
    }
}
