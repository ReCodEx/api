<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use GuzzleHttp\Client;
use Nette\Utils\Json;
use App\Exception\SubmissionFailedException;

use GuzzleHttp\Exception\RequestException;
use ZMQ;
use ZMQSocket;
use ZMQContext;
use ZMQSocketException;

/**
 * @ORM\Entity
 */
class Submission implements JsonSerializable
{
    use \Kdyby\Doctrine\Entities\MagicAccessors;

    const REMOTE_FILE_SERVER_URL = 'http://195.113.17.8:9999';
    const ZMQ_SERVER_URL = 'tcp://195.113.17.8:9658';

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
     * @param  string $name   Name of the exercise
     * @return User
     */
    public static function createSubmission($note, ExerciseAssignment $assignment, User $user, array $files) {
        $entity = new Submission;
        $entity->exerciseAssignment = $assignment;
        $entity->user = $user;
        $entity->note = $note;
        $entity->submittedAt = new \DateTime;
        $entity->files = new ArrayCollection;
        foreach ($files as $file) {
          if ($file->submission !== NULL) {
            // the file was already used before
            // @todo Should it fail at this moment??
          }

          $entity->files->add($file);
          $file->submission = $entity;
        }

        return $entity;
    }

    public function submit() {
      $files = $this->prepareFilesForSendingToRemoteServer($this, $this->files);
      $remotePaths = $this->sendFilesToRemoteFileServer($this->id, $files);
      if (!isset($remotePaths->archive_path) || !isset($remotePaths->result_path)) {
        throw new SubmissionFailedException('Remote file server broke the communication protocol');
      }

      $this->resultsUrl = self::REMOTE_FILE_SERVER_URL . $remotePaths->result_path;
      return $this->startEvaluation($this->id, $remotePaths->archive_path, $remotePaths->result_path);
    }

    private function prepareFilesForSendingToRemoteServer($submission, $files) {
      $filesToSubmit = array_map(function ($file) {
        return [
          'name' => $file->name,
          'filename' => $file->name,
          'contents' => fopen($file->filePath, 'r')
        ];
      }, $files->toArray());
      
      $jobConfigFile = [
        'name' => 'job-config.yml',
        'filename' => 'job-config.yml',
        'contents' => fopen($submission->exerciseAssignment->jobConfigFilePath, 'r')
      ];

      array_push($filesToSubmit, $jobConfigFile);
      return $filesToSubmit;
    }

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
     * @param string
     * @param string
     * @param string
     * @return bool     Evaluation has been started on remote server when returns TRUE.
     */
    private function startEvaluation($submissionId, $archiveRemotePath, $resultRemotePath) {
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
