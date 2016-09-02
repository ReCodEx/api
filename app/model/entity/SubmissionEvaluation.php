<?php

namespace App\Model\Entity;

use App\Exception\SubmissionEvaluationFailedException;
use App\Exception\NotFoundException;
use App\Model\Helpers\ResultsTransform as RT;
use App\Model\Helpers\SimpleScoreCalculator;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;


use Symfony\Component\Yaml\Yaml;

/**
 * @ORM\Entity
 */
class SubmissionEvaluation implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

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
   * @ORM\OneToOne(targetEntity="Submission", mappedBy="evaluation")
   */
  protected $submission;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $initFailed;

  /**
   * @ORM\Column(type="float")
   */
  protected $score;
  public function isCorrect() { return $this->score > 0; }

  /**
   * @ORM\Column(type="integer")
   */
  protected $points;

  /**
   * @ORM\Column(type="integer", nullable=true)
   */
  protected $bonusPoints;

  public function getTotalPoints() {
    return $this->getPoints() + $this->getBonusPoints();
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isValid;
  public function isValid() { return $this->isValid; }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $evaluationFailed;

  /**
   * @ORM\Column(type="text")
   */
  protected $resultYml;

  /**
   * @ORM\OneToMany(targetEntity="TestResult", mappedBy="submissionEvaluation", cascade={"persist"})
   */
  protected $testResults;

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "evaluatedAt" => $this->evaluatedAt->getTimestamp(),
      "score" => $this->score,
      "points" => $this->points,
      "bonusPoints" => $this->bonusPoints,
      "maxPoints" => $this->submission->getExerciseAssignment()->getMaxPoints($this->evaluatedAt),
      "initFailed" => $this->initFailed,
      "isValid" => $this->isValid,
      "isCorrect" => $this->isCorrect(),
      "evaluationFailed" => $this->evaluationFailed,
      "testResults" => $this->testResults->toArray()
    ];
  }

  /**
   * Loads and processes the results of the submission.
   * @param  Submission $submission   The submission
   * @param  string     $resultYmlContent   Results YML raw file content
   * @return SubmissionEvaluation
   */
  public static function loadEvaluation(Submission $submission, $resultYmlContent) {
    if (!$submission->resultsUrl) {
      throw new SubmissionEvaluationFailedException("The results archive cannot be located.");
    }

    // download the results ZIP file from the server
    try {
      $zipFileContent = self::downloadResults($submission->resultsUrl);
    } catch (ClientException $e) {
      throw new NotFoundException('Results are not available (yet).');
    }
    
    $jobConfig = $submission->getParsedJobConfig();
    
    try {
      $tasksResults = Yaml::parse($resultYmlContent);
    } catch (\Exception $e) { // @todo: Catch specific exceptions (unzipping and parsing)
      throw new SubmissionEvaluationFailedException("The results received from the file server are malformed.");
    }

    $evaluationFailed = $tasksResults === FALSE;
    $evaluation = self::createSubmissionEvaluation($submission, $evaluationFailed, $resultYmlContent);

    // when the evaluation fails, there is no need (and no way of how) to analyze results of individual tests
    if ($evaluationFailed) {
      return $evaluation;
    }

    // determine whether the submission was compiled or initialised in any other way successfully
    $evaluation->initFailed = self::allInitialisationStepsOK($tasksResults) === FALSE;
    if ($evaluation->initFailed === TRUE) {
      $evaluation->score = 0;
      $evaluation->points = 0;
      $evaluation->isCorrect = FALSE;
      return $evaluation;
    }

    $testsResults = RT::transformLowLevelInformation($jobConfig, $tasksResults);
    foreach ($testsResults as $name => $result) {
      if (!isset($result[RT::FIELD_LIMITS]) || !isset($result[RT::FIELD_LIMITS][$submission->getHardwareGroup()])) {
        // @todo what to do? is there any sort of default limits?
      }

      $limits = $result[RT::FIELD_LIMITS][$submission->getHardwareGroup()];
      $judgeOutput = isset($result[RT::FIELD_JUDGE_OUTPUT]) && !empty($result[RT::FIELD_JUDGE_OUTPUT]) ? $result[RT::FIELD_JUDGE_OUTPUT] : "";
      $testResult = TestResult::createTestResult(
        $evaluation,
        $name,
        $result[RT::FIELD_STATUS],
        $result[RT::FIELD_SCORE],
        $judgeOutput,
        $result[RT::FIELD_STATS],
        $limits
      );
      $evaluation->testResults->add($testResult);
    }

    $evaluation->score = self::computeScore($submission->getExerciseAssignment(), $evaluation->testResults);
    $evaluation->points = $evaluation->score * $submission->getMaxPoints();

    return $evaluation;
  }

  /**
   * Create an entity from the given values.
   * @param   Submission            $submission
   * @param   bool                  $evaluationFailed
   * @return  SubmissionEvaluation
   */
  public static function createSubmissionEvaluation(Submission $submission, bool $evaluationFailed, string $resultYmlContent) {
    $entity = new SubmissionEvaluation;
    $entity->evaluatedAt = new \DateTime;
    $entity->isValid = TRUE;
    $entity->evaluationFailed = FALSE;
    $entity->resultYml = $resultYmlContent;
    $entity->submission = $submission;
    $submission->setEvaluation($entity);
    $entity->testResults = new ArrayCollection;

    return $entity;
  }

  /**
   * Analyze whether the source code(s) was (were) compiled successfully or can be interpreted without any syntax and other simillar errors.
   * @param array $tasksResults Results for each task
   * @return bool All initialisation tasks finished with status OK
   */
  public static function allInitialisationStepsOK(array $tasksResults) {
    return array_reduce($tasksResults, function ($carry, $task) {
      return $carry &&
        (isset($task[RT::FIELD_TYPE]) && $task[RT::FIELD_TYPE] === RT::TYPE_INITIATION)
          ? $task[RT::FIELD_STATUS] === RT::STATUS_OK
          : TRUE;
    }, TRUE);
  }

  /**
   * Calculate the score for the submitted solution based on the results of individual tests and the configuration of the assignment.
   * @param ExerciseAssignment  $assignment   The assignment
   * @param ArrayCollection     $testResults  The results
   * @return float                            The calculated score of the submission
   */
  public static function computeScore(ExerciseAssignment $assignment, ArrayCollection $testResults) {
    $calculator = new SimpleScoreCalculator();
    return $calculator->computeScore($assignment->getScoreConfig(), $testResults);
  }

}
