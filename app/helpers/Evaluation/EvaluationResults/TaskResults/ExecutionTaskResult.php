<?php

namespace App\Helpers\EvaluationResults;

use App\Exceptions\ResultsLoadingException;

/**
 * Results of execution tasks (usually user binaries)
 */
class ExecutionTaskResult extends TaskResult
{
    private const SANDBOX_RESULTS_KEY = "sandbox_results";

    /** @var ISandboxResults Statistics of the execution */
    private $stats;

    /**
     * Constructor
     * @param array $data Raw result data
     * @throws ResultsLoadingException
     */
    public function __construct(array $data)
    {
        parent::__construct($data);

        if (!$this->isSkipped()) {
            if (!isset($data[self::SANDBOX_RESULTS_KEY])) {
                throw new ResultsLoadingException(
                    "Execution task '{$this->getId()}' does not contain sandbox results."
                );
            }

            if (!is_array($data[self::SANDBOX_RESULTS_KEY])) {
                throw new ResultsLoadingException(
                    "Execution task '{$this->getId()}' does not contain array of sandbox results."
                );
            }

            $this->stats = new SandboxResults($data[self::SANDBOX_RESULTS_KEY]);
        } else {
            $this->stats = new SkippedSandboxResults();
        }
    }

    /**
     * Get parsed statistics of execution
     * @return ISandboxResults Statistics of the execution
     */
    public function getSandboxResults(): ISandboxResults
    {
        return $this->stats;
    }

    /**
     * The exit code of the executed program
     * @return int The code
     */
    public function getExitCode(): int
    {
        return $this->getSandboxResults()->getExitCode();
    }
}
