<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use GuzzleHttp\Client;
use Kdyby\Doctrine\Entities\MagicAccessors;
use Nette\Utils\Json;
use Nette\Utils\Arrays;

use App\Exception\SubmissionFailedException;
use App\Exception\MalformedJobConfigException;
use App\Exception\ForbiddenRequestException;

use GuzzleHttp\Exception\RequestException;
use ZMQ;
use ZMQSocket;
use ZMQContext;
use ZMQSocketException;
use Symfony\Component\Yaml\Yaml;

/**
 * @ORM\Entity
 */
class Submission implements JsonSerializable
{
    use MagicAccessors;

    const REMOTE_FILE_SERVER_URL = 'http://195.113.17.8:9999'; // @todo place in a configuration file
    const ZMQ_SERVER_URL = 'tcp://195.113.17.8:9658'; // @todo place in a configuration file

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
     * @ORM\Column(type="text")
     */
    protected $resultsUrl;

    /**
     * @var ExerciseAssignment
     * @ORM\ManyToOne(targetEntity="ExerciseAssignment")
     * @ORM\JoinColumn(name="exercise_assignment_id", referencedColumnName="id")
     */
    protected $exerciseAssignment;

    public function getExerciseAssignment() {
        return $this->exerciseAssignment;
    }

    public function getMaxPoints() {
      return $this->exerciseAssignment->getMaxPoints($this->submittedAt);
    }

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

    /** @var null|int This value is not persisted to the database - it is loaded when the job config YML file is examined. */
    private $tasksCount = NULL;

    /**
     * @return null|int
     */
    public function getTasksCount() {
      return $this->tasksCount;
    }

    /**
     * @return array
     */
    public function jsonSerialize() {
      return [
        'id' => $this->id,
        'user' => $this->getUser(),
        'note' => $this->note,
        'exerciseAssignment' => $this->getExerciseAssignment(),
        'submittedAt' => $this->submittedAt
      ];
    }

    /**
     * The name of the user
     * @param string $note
     * @param ExerciseAssignment $assignment
     * @param User $user          The user who submits the solution
     * @param User $loggedInUser  The logged in user - might be the student or his/her supervisor
     * @param array $files
     * @return Submission
     * @internal param string $name Name of the exercise
     */
    public static function createSubmission(string $note, ExerciseAssignment $assignment, User $user, User $loggedInUser, array $files) {
      // the 'user' must be a student and the 'loggedInUser' must be either this student, or a supervisor of this group
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
        throw new ForbiddenRequestException('It is after the deadline, you cannot submit solutions any more. Contact your supervisor for assistance.');
      }

      // now that the conditions for submission are validated, here comes the easy part:
      $entity = new Submission;
      $entity->exerciseAssignment = $assignment;
      $entity->user = $user;
      $entity->note = $note;
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

    /**
     * @return bool
     * @throws SubmissionFailedException
     */
    public function submit() {
      $files = $this->prepareFilesForSendingToRemoteServer($this, $this->files);
      $remotePaths = $this->sendFilesToRemoteFileServer($this->id, $files);
      if (!isset($remotePaths->archive_path) || !isset($remotePaths->result_path)) {
        throw new SubmissionFailedException('Remote file server broke the communication protocol');
      }

      $this->resultsUrl = self::REMOTE_FILE_SERVER_URL . $remotePaths->result_path;
      return $this->startEvaluation($this->id, $remotePaths->archive_path, $remotePaths->result_path);
    }

    /**
     * @param Submission $submission
     * @param array $files
     * @return array
     */
    private function prepareFilesForSendingToRemoteServer(Submission $submission, $files) {
      $filesToSubmit = array_map(function ($file) {
        return [
          'name' => $file->name,
          'filename' => $file->name,
          'contents' => fopen($file->filePath, 'r')
        ];
      }, $files->toArray());
      
      $jobConfigYml = $this->getJobConfig($submission);
      $jobConfigFile = [
        'name' => 'job-config.yml',
        'filename' => 'job-config.yml',
        'contents' => $jobConfigYml
      ];

      array_push($filesToSubmit, $jobConfigFile);
      return $filesToSubmit;
    }

    /**
     * @param Submission $submission  The submission entity
     * @throws MalformedJobConfigException
     * @return string
     */
    private function getJobConfig(Submission $submission): string {
      $configFileName = realpath($submission->exerciseAssignment->getJobConfigFilePath());
      if ($configFileName === FALSE) {
        throw new MalformedJobConfigException('The configuration file does not exist on the server.');
      }

      $jobConfig = file_get_contents($configFileName);
      if ($jobConfig === FALSE) {
        throw new MalformedJobConfigException('Cannot open the configuration file for reading.');
      }

      try {
        $parsedConfig = Yaml::parse($jobConfig);
      } catch (ParseException $e) {
        throw new MalformedJobConfigException('Assignment configuration file is not a valid YAML file and it cannot be parsed.');
      }

      // update the job-id field 
      $parsedConfig['submission']['job-id'] = $submission->getId();

      // count the number of tasks - so we can calculate the progress
      // of evaluation in the browser
      $this->tasksCount = count($parsedConfig['tasks']); 

      return YAML::dump($parsedConfig);
    }

    /**
     * @param $submissionId
     * @param $files
     * @return mixed
     * @throws SubmissionFailedException
     */
    private function sendFilesToRemoteFileServer($submissionId, $files) {
      try {
        $client = new Client([ 'base_uri' => self::REMOTE_FILE_SERVER_URL ]);
        $response = $client->request('POST', "/submissions/$submissionId", [ 'multipart' => $files ]);

        if ($response->getStatusCode() === 200) {
          return Json::decode($response->getBody());
        } else {
          throw new SubmissionFailedException('Remote file server is not working correctly');
        }
      } catch (RequestException $e) {
        throw new SubmissionFailedException('Cannot connect to remote file server');
      }
    }

    /**
     * @param $submissionId
     * @param $archiveRemotePath
     * @param $resultRemotePath
     * @return bool Evaluation has been started on remote server when returns TRUE.
     * @throws SubmissionFailedException
     * @internal param $string
     * @internal param $string
     * @internal param $string
     */
    private function startEvaluation(string $submissionId, string $archiveRemotePath, string $resultRemotePath) {
      try {
        $queue = new ZMQSocket(new ZMQContext, ZMQ::SOCKET_REQ, $submissionId);
        $queue->connect(self::ZMQ_SERVER_URL);
        $queue->sendmulti([
          'eval',
          $submissionId,
          'hwgroup=group1',
          '',
          self::REMOTE_FILE_SERVER_URL . $archiveRemotePath,
          self::REMOTE_FILE_SERVER_URL . $resultRemotePath
        ]);

        $response = $queue->recv();
        return $response === 'accept';
      } catch (ZMQSocketException $e) {
        throw new SubmissionFailedException('Communication with backend broker failed.');
      }
    }
}
