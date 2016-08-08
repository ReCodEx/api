<?php

namespace App\Model\Entity;

use App\Exception\SubmissionEvaluationFailedException;
use App\Exception\NotFoundException;
use App\Model\Helpers\ResultsTransform as RT;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

use ZipArchive;
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
   * @ORM\Column(type="float")
   */
  protected $score;

  /**
   * @ORM\Column(type="smallint")
   */
  protected $points;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isValid;
  public function isValid() { return $this->isValid; }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isCorrect;
  public function isCorrect() { return $this->isCorrect; }

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
      "maxPoints" => $this->submission->getExerciseAssignment()->getMaxPoints($this->evaluatedAt),
      "isValid" => $this->isValid,
      "isCorrect" => $this->isCorrect,
      "evaluationFailed" => $this->evaluationFailed,
      "testResults" => $this->testResults->toArray()
    ];
  }

  /**
   * Loads and processes the results of the submission.
   * @param  Submission $submission   The submission
   * @return SubmissionEvaluation
   */
  public static function loadEvaluation(Submission $submission) {
    if (!$submission->resultsUrl) {
      throw new SubmissionEvaluationFailedException("The results archive cannot be located.");
    }

    // download the results ZIP file from the server
    try {
      $zipFileContent = self::downloadResults($submission->resultsUrl);
    } catch (ClientException $e) {
      throw new NotFoundException('Results are not available (yet).');
    }
    
    $jobConfig = $submission->getJobConfig(); // this handles errors well
    
    try {
      $resultYmlContent = self::getResultYmlContent($zipFileContent);  
      $tasksResults = Yaml::parse($resultYmlContent);
    } catch (\Exception $e) { // @todo: Catch specific exceptions (unzipping and parsing)
      throw new SubmissionEvaluationFailedException("The results received from the file server are malformed.");
    }

    $evaluationFailed = $tasksResults === FALSE;
    $evaluation = self::createSubmissionEvaluation($submission, $evaluationFailed, $resultYmlContent);

    if (!$evaluationFailed) {
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
      $evaluation->isCorrect = self::isSufficientScore($submission->getExerciseAssignment(), $evaluation->score);
      $evaluation->points = $evaluation->isCorrect ? $evaluation->score * $submission->getMaxPoints() : 0;
    }

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
   * Downloads the contents of a file at the given URL
   * @param   string $url   URL of the file
   * @return  string        Contents of the file
   */
  private static function downloadResults(string $url) {
    $client = new Client();
    $response = $client->request("GET", $url);
    return $response->getBody();
  }

  /**
   * Extracts the contents of the downloaded ZIP file
   * @param   string $zipFileContent    Content of the zip file
   * @return  string
   */
  private static function getResultYmlContent($zipFileContent) {
    // the contents must be saved to a tmp file first
    $tmpFile = tempnam(sys_get_temp_dir(), "ReC");
    file_put_contents($tmpFile, $zipFileContent);
    $zip = new ZipArchive;
    if (!$zip->open($tmpFile)) {
      throw new SubmissionEvaluationFailedException("Cannot open results from remote file server.");
    }

    $yml = $zip->getFromName("result/result.yml");
    if ($yml === FALSE) {
      throw new SubmissionEvaluationFailedException("Results YAML file is missing in the archive received from remote FS.");
    }

    // a bit of a cleanup
    $zip->close();
    unlink($tmpFile);

    return $yml;
  }

  public static function computeScore(ExerciseAssignment $assignment, ArrayCollection $testResults) {
    // @todo Unit test this !!
    // @todo
    return 0;
  }

  public static function isSufficientScore(ExerciseAssignment $assignment, float $score) {
    // @todo
    return FALSE;
  }

}
