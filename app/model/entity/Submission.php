<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;
use Nette\Utils\Json;
use Nette\Utils\Arrays;

use App\Exception\BadRequestException;
use App\Exception\ForbiddenRequestException;
use App\Exception\MalformedJobConfigException;
use App\Exception\SubmissionFailedException;

use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Yaml\Yaml;

/**
 * @ORM\Entity
 */
class Submission implements JsonSerializable
{
    use MagicAccessors;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $submittedAt;

    public function getSubmittedAt() {
      return $this->submittedAt;
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $note;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $resultsUrl;

    /**
     * @var ExerciseAssignment
     * @ORM\ManyToOne(targetEntity="ExerciseAssignment")
     * @ORM\JoinColumn(name="exercise_assignment_id", referencedColumnName="id")
     */
    protected $exerciseAssignment;

    public function getMaxPoints() {
      return $this->exerciseAssignment->getMaxPoints($this->submittedAt);
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $hardwareGroup;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    public function getUser() {
      return $this->user;
    }

    /**
     * @ORM\OneToMany(targetEntity="UploadedFile", mappedBy="submission")
     */
    protected $files;

    public function getFiles() {
      return $this->files;
    }

    /**
     * @ORM\OneToOne(targetEntity="SubmissionEvaluation", inversedBy="submission")
     * @ORM\JoinColumn(name="submission_evaluation_id", referencedColumnName="id")
     */
    protected $evaluation;

    public function getEvaluation() {
      return $this->evaluation;
    }

    public function setEvaluation(SubmissionEvaluation $evaluation) {
      $this->evaluation = $evaluation;
    }

    public function getEvaluationStatus() {
      $eval = $this->getEvaluation();
      if ($eval === NULL) {
        return "work-in-progress";
      } elseif ($eval->isValid() === FALSE) {
        return "evaluation-failed";
      } elseif ($eval->isCorrect() === TRUE) {
        return "done";
      } else {
        return "failed";
      }
    }

    public function getTotalPoints() {
      $eval = $this->getEvaluation();
      if (!$eval) {
        return 0;
      }

      return $eval->getPoints() + $eval->getBonusPoints();
    }

    /**
     * @return array
     */
    public function jsonSerialize() {
      return [
        "id" => $this->id,
        "userId" => $this->getUser()->getId(),
        "note" => $this->note,
        "exerciseAssignmentId" => $this->getExerciseAssignment()->getId(),
        "submittedAt" => $this->submittedAt->getTimestamp(),
        "evaluationStatus" => $this->getEvaluationStatus(),
        "evaluation" => $this->getEvaluation(),
        "files" => $this->getFiles()->toArray()
      ];
    }

    /**
     * The name of the user
     * @param string $note
     * @param ExerciseAssignment $assignment
     * @param User $user          The user who submits the solution
     * @param User $loggedInUser  The logged in user - might be the student or his/her supervisor
     * @param string $hardwareGroup
     * @param array $files
     * @return Submission
     */
    public static function createSubmission(string $note, ExerciseAssignment $assignment, User $user, User $loggedInUser, string $hardwareGroup, array $files) {
      // the "user" must be a student and the "loggedInUser" must be either this student, or a supervisor of this group
      if ($assignment->canAccessAsStudent($user) === FALSE
        ) {
        // || ($user->getId() === $loggedInUser->getId() // @todo fix this mess
        //     && $assignment->canAccessAsSupervisor($loggedInUser) === FALSE)) {
        throw new ForbiddenRequestException("{$user->getName()} cannot submit solutions for this assignment.");
      }

      if ($assignment->canReceiveSubmissions() === FALSE) {
        // @todo Throw some more meaningful error (HTTP 402 - payment required)
        throw new ForbiddenRequestException("Your institution '{$assignment->getGroup()->getInstance()->getName()}' does not have a valid licence and you cannot submit solutions for any assignment in this group '{$assignment->getGroup()->getName()}'. Contact your supervisor for assistance.");
      }

      if ($assignment->isAfterDeadline() === TRUE
        && $assignment->isSupervisorOf($loggedInUser) === FALSE) { // supervisors can force-submit even after the deadline 
        throw new ForbiddenRequestException("It is after the deadline, you cannot submit solutions any more. Contact your supervisor for assistance.");
      }

      // now that the conditions for submission are validated, here comes the easy part:
      $entity = new Submission;
      $entity->exerciseAssignment = $assignment;
      $entity->user = $user;
      $entity->note = $note;
      $entity->hardwareGroup = $hardwareGroup;
      $entity->submittedAt = new \DateTime;
      $entity->files = new ArrayCollection;
      foreach ($files as $file) {
        if ($file->submission !== NULL) {
          // the file was already used before and that is not allowed
          throw new BadRequestException("The file {$file->getId()} was already used in a different submission. If you want to use this file, reupload it to the server.");
        }

        $entity->files->add($file);
        $file->submission = $entity;
      }

      return $entity;
    }

    private $parsedJobConfig = NULL;
    private $jobConfig = NULL;

    /**
     * @throws MalformedJobConfigException
     * @return array Parsed YAML config with updated job-id
     */
    public function getParsedJobConfig(): array {
      if ($this->parsedJobConfig === NULL) {
        $jobConfig = $this->exerciseAssignment->getJobConfig();

        try {
          $parsedConfig = Yaml::parse($jobConfig);
        } catch (ParseException $e) {
          throw new MalformedJobConfigException("Assignment configuration file is not a valid YAML file and it cannot be parsed.");
        }

        $parsedConfig["submission"]["job-id"] = $this->getId(); // update the job-id field
        $this->parsedJobConfig = $parsedConfig; // cache the content of the config so no more parsing is needed
      }

      return $this->parsedJobConfig;
    }

    /**
     * @return string YAML config for the evaluation server for this submission (updated job-id)
     */
    public function getJobConfig(): string {
      if ($this->jobConfig === NULL) {
        // the config must be first parsed and updated
        $this->jobConfig = Yaml::dump($this->getParsedJobConfig());
      }

      return $this->jobConfig;
    }

    /**
     * Count the number of tasks from the job config - so we can calculate the progress
     * of evaluation in the browser
     */
    public function getTasksCount() {
      if ($this->parsedJobConfig === NULL) {
        $this->parsedJobConfig = $this->getJobConfig();
      }

      return count($this->parsedJobConfig["tasks"]);
    }
}
