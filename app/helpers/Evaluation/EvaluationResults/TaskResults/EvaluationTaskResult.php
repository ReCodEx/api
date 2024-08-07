<?php

namespace App\Helpers\EvaluationResults;

use App\Exceptions\ResultsLoadingException;
use Nette\Utils\Validators;

/**
 * Results of evaluation tasks (judges)
 */
class EvaluationTaskResult extends TaskResult
{
    /** @var float|null Explicit score from the results */
    private $score = null;

    /**
     * Constructor
     * @param array $data Raw result data
     * @throws ResultsLoadingException
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        list($this->score, $this->stdout) = self::parseJudgeOutput($this->stdout);
    }

    /**
     * Parses the judge output and yields the result
     * @return float The score
     */
    public function getScore(): float
    {
        if ($this->score !== null) {
            return $this->score;
        }
        return parent::getScore();
    }


    /**
     * Parse given output from judge into score and following log.
     * @param null|string $judgeStdout
     * @return array pair of score (?float) and remaining log from judge (?string)
     */
    public static function parseJudgeOutput(?string $judgeStdout): array
    {
        $score = null;
        $judgeLog = $judgeStdout;

        if (!empty($judgeStdout)) {
            $splitted = preg_split('/\s+/', $judgeStdout, 2);
            if (Validators::isNumeric($splitted[0]) === true) {
                $score = min(TaskResult::MAX_SCORE, max(TaskResult::MIN_SCORE, floatval($splitted[0])));
                $judgeLog = empty($splitted[1]) ? '' : trim($splitted[1]);
            }
        }

        return [$score, $judgeLog];
    }
}
