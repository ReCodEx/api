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
 */
class SolutionEvaluation
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
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
            "id" => $this->getId(),
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

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getEvaluatedAt(): DateTime
    {
        return $this->evaluatedAt;
    }

    public function setEvaluatedAt(DateTime $evaluatedAt): void
    {
        $this->evaluatedAt = $evaluatedAt;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getTestResults(): Collection
    {
        return $this->testResults;
    }

    public function getInitFailed(): bool
    {
        return $this->initFailed;
    }

    public function setInitFailed(bool $initFailed): void
    {
        $this->initFailed = $initFailed;
    }

    public function setScoreConfig(?ExerciseScoreConfig $scoreConfig): void
    {
        $this->scoreConfig = $scoreConfig;
    }

    public function setInitiationOutputs(string $initiationOutputs): void
    {
        $this->initiationOutputs = $initiationOutputs;
    }
}
