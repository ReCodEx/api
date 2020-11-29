<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Helpers\EvaluationResults as ER;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getTestName()
 * @method float getScore()
 * @method int getUsedMemory()
 * @method int getUsedMemoryLimit()
 * @method float getUsedWallTime()
 * @method float getUsedWallTimeLimit()
 * @method float getUsedCpuTime()
 * @method float getUsedCpuTimeLimit()
 * @method string getStatus()
 * @method string getMessage()
 * @method int getExitCode()
 * @method ?int getExitSignal()
 * @method bool getCpuTimeExceeded()
 * @method bool getWallTimeExceeded()
 * @method bool getMemoryExceeded()
 * @method SolutionEvaluation getSolutionEvaluation()
 */
class TestResult
{
    use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

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

    public function getData(bool $canViewLimits, bool $canViewValues, bool $canViewJudgeOutput)
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
        $judgeLog = null;

        if ($canViewLimits) {
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
        if ($canViewValues) {
            $wallTime = $this->usedWallTime;
            $cpuTime = $this->usedCpuTime;
            $memory = $this->usedMemory;
        }
        if ($canViewJudgeOutput) {
            // FIXME
            $judgeLog = $this->judgeStdout;
        }

        return [
            "id" => $this->id,
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
            "judgeLog" => $judgeLog
        ];
    }
}
