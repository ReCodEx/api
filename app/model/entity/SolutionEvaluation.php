<?php

namespace App\Model\Entity;

use App\Helpers\EvaluationResults\EvaluationResults;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method DateTime getEvaluatedAt()
 * @method bool getEvaluationFailed()
 * @method float getScore()
 * @method int getPoints()
 * @method setPoints(int $points)
 * @method setScore(float $score)
 * @method Collection getTestResults()
 * @method ReferenceSolutionSubmission getReferenceSolutionSubmission()
 * @method bool getInitFailed()
 */
class SolutionEvaluation
{
    use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $evaluatedAt;

    /**
     * If true, the solution cannot be compiled.
     * @ORM\Column(type="boolean")
     */
    protected $initFailed;

    /**
     * @ORM\Column(type="float")
     */
    protected $score;

    /**
     * @ORM\Column(type="integer")
     */
    protected $points;

    /**
     * @ORM\Column(type="text")
     */
    protected $initiationOutputs;

    /**
     * @ORM\OneToMany(targetEntity="TestResult", mappedBy="solutionEvaluation", cascade={"persist", "remove"})
     */
    protected $testResults;

    public function getData(bool $canViewLimits, bool $canViewValues = false, bool $canViewJudgeOutput = false)
    {
        $testResults = $this->testResults->map(
            function (TestResult $res) use ($canViewLimits, $canViewValues, $canViewJudgeOutput) {
                return $res->getData($canViewLimits, $canViewValues, $canViewJudgeOutput);
            }
        )->getValues();

        return [
            "id" => $this->id,
            "evaluatedAt" => $this->evaluatedAt->getTimestamp(),
            "score" => $this->score,
            "points" => $this->points,
            "initFailed" => $this->initFailed,
            "initiationOutputs" => $this->initiationOutputs,
            "testResults" => $testResults
        ];
    }

    /**
     * Loads and processes the results of the submission.
     * @param EvaluationResults $results The interpreted results
     */
    public function __construct(EvaluationResults $results)
    {
        $this->evaluatedAt = new \DateTime();
        $this->initFailed = !$results->initOK();
        $this->score = 0;
        $this->points = 0;
        $this->testResults = new ArrayCollection();
        $this->initiationOutputs = $results->getInitiationOutputs();

        // set test results
        foreach ($results->getTestsResults() as $result) {
            $testResult = new TestResult($this, $result);
            $this->testResults->add($testResult);
        }
    }
}
