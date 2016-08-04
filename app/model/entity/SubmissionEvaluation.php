<?php

namespace App\Model\Entity;

use App\Exception\SubmissionEvaluationFailedException;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

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

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isCorrect;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $evaluationFailed;

  /**
   * @ORM\Column(type="text")
   */
  protected $resultYml;

  public function jsonSerialize() {
    return [
      'id' => $this->id,
      'evaluatedAt' => $this->evaluatedAt->getTimestamp(),
      'score' => $this->score,
      'points' => $this->points,
      'maxPoints' => $this->submission->getExerciseAssignment()->getMaxPoints($this->evaluatedAt),
      'isValid' => $this->isValid,
      'isCorrect' => $this->isCorrect,
      'evaluationFailed' => $this->evaluationFailed
    ];
  }

  /**
   * Loads and processes the results of the submission.
   * @param  Submission $submission   The submission
   * @return SubmissionEvaluation
   */
  public static function loadEvaluation(Submission $submission) {
    // download the results ZIP file from the server
    $zipFileContent = self::downloadResults($submission->resultsUrl);
    $resultYmlContent = self::getResultYmlContent($zipFileContent);
    
    $evaluationFailed = FALSE;
    $score = NULL;
    $points = NULL;
    $isCorrect = NULL;

    // parse the YML
    $yml = Yaml::parse($resultYmlContent);
    if ($yml === FALSE) {
      $evaluationFailed = TRUE;
    } else {
      $score = self::getScore($submission->getExerciseAssignment(), $yml);
      $isCorrect = self::isCorrect($score, $yml);
      $points = $isCorrect ? $score * $submission->getMaxPoints() : 0;
    }
    
    return self::createSubmissionEvaluation($submission, $score, $points, $isCorrect, $evaluationFailed, $resultYmlContent);
  }

  /**
   * Create an entity from the given values.
   * @param   Submission            $submission
   * @param   float                 $score
   * @param   int                   $points
   * @param   bool                  $isCorrect
   * @param   bool                  $evaluationFailed
   * @return  SubmissionEvaluation
   */
  public static function createSubmissionEvaluation(Submission $submission, float $score, int $points, bool $isCorrect, bool $evaluationFailed, string $resultYmlContent) {
    $entity = new SubmissionEvaluation;
    $entity->score = $score;
    $entity->points = $points;
    $entity->evaluatedAt = new \DateTime;
    $entity->isCorrect = $isCorrect;
    $entity->isValid = TRUE;
    $entity->evaluationFailed = FALSE;
    $entity->resultYml = $resultYmlContent;
    $entity->submission = $submission;
    $submission->setEvaluation($entity);

    return $entity;
  }

  /**
   * Downloads the contents of a file at the given URL
   * @param   string $url   URL of the file
   * @return  string        Contents of the file
   */
  private static function downloadResults(string $url) {
    $client = new Client();
    $response = $client->request('GET', $url);
    return $response->getBody();
  }

  /**
   * Extracts the contents of the downloaded ZIP file
   * @param   string $zipFileContent    Content of the zip file
   * @return  string
   */
  private static function getResultYmlContent($zipFileContent) {
    // the contents must be saved to a tmp file first
    $tmpFile = tempnam(sys_get_temp_dir(), 'ReC');
    file_put_contents($tmpFile, $zipFileContent);
    $zip = new ZipArchive;
    if (!$zip->open($tmpFile)) {
      throw new SubmissionEvaluationFailedException('Cannot open results from remote file server.');
    }

    $yml = $zip->getFromName('result/result.yml');

    // a bit of a cleanup
    $zip->close();
    unlink($tmpFile);

    return $yml;
  }

  private static function getScore(ExerciseAssignment $assignment, array $result) {
    return 0;
  }

  private static function isCorrect(float $score, array $result) {
    return FALSE;
  }

}
