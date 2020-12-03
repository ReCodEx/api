<?php

namespace App\Model\Entity;

use App\Helpers\EvaluationResults\EvaluationResults;
use App\Model\View\Helpers\SubmissionViewOptions;
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
 * @method int getPoints()
 * @method setPoints(int $points)
 * @method Collection getTestResults()
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

    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * @ORM\ManyToOne(targetEntity="ExerciseScoreConfig")
     */
    protected $scoreConfig;

    public function getScoreConfig(): ?ExerciseScoreConfig
    {
        return $this->scoreConfig;
    }

    /**
     * Sets the score and remembers the exercise score config which was used to compute it.
     * @param float $score New value of the score
     * @param ExerciseScoreConfig|null $scoreConfig The config entity used to compute the score
     */
    public function setScore(float $score, ExerciseScoreConfig $scoreConfig = null): void
    {
        $this->score = $score;
        $this->scoreConfig = $scoreConfig;
    }

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

    public function getDataForView(SubmissionViewOptions $options)
    {
        $testResults = $this->testResults->map(
            function (TestResult $res) use ($options) {
                return $res->getDataForView($options);
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
        $this->scoreConfig = null;
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
