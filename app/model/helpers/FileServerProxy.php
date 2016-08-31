<?php

namespace App\Model\Helpers;

use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Doctrine\Common\Collections\ArrayCollection;
use GuzzleHttp\Client;
use App\Exception\SubmissionFailedException;

/**
 * @author  Šimon Rozsíval <simon@rozsival.com>
 */
class FileServerProxy {

  /** @var string */
  private $remoteServerAddress;

  /** @var string */
  private $username;

  /** @var string */
  private $password;

  /** @var string */
  private $jobConfigFileName;

  public function __construct(array $config) {
    $this->remoteServerAddress = $config['address'];
    $this->username = $config['username'];
    $this->password = $config['password'];
    $this->jobConfigFileName = $config['jobConfigFileName'];
  }

  /**
   * @param string   $submissionId
   * @param string[] $files
   * @return mixed
   * @throws SubmissionFailedException
   */
  public function sendFiles(string $submissionId, string $jobConfig, array $files) {
    $filesToSubmit = $this->prepareFiles($jobConfig, $files);

    try {
      $client = new Client([ "base_uri" => $this->remoteServerAddress ]);
      $response = $client->request("POST", "/submissions/$submissionId",
        [ "multipart" => $filesToSubmit, "auth" => [ $this->username, $this->password ] ]);

      if ($response->getStatusCode() === 200) {
        try {
          $paths = Json::decode($response->getBody());
        } catch (JsonException $e) {
          throw new SubmissionFailedException("Remote file server did not respond with a valid JSON response.");
        }

        if (!isset($paths->archive_path) || !isset($paths->result_path)) {
          throw new SubmissionFailedException("Remote file server broke the communication protocol");
        }

        return [
          $this->remoteServerAddress . $paths->archive_path,
          $this->remoteServerAddress . $paths->result_path
        ];
      } else {
        throw new SubmissionFailedException("Remote file server is not working correctly");
      }
    } catch (RequestException $e) {
      throw new SubmissionFailedException("Cannot connect to remote file server");
    }
  }


  /**
   * @param Submission $submission
   * @param array $files
   * @return array
   */
  private function prepareFiles(string $jobConfig, array $files) {
    $filesToSubmit = array_map(function ($file) {
      if (!file_exists($file->filePath)) {
        throw new SubmissionFailedException("File $file->filePath does not exist on the server.");
      }

      if ($file->name === $this->jobConfigFileName) {
        throw new SubmissionFailedException("User is not allowed to upload a file with the name of $this->jobConfigFileName");
      }

      return [
        "name" => $file->name,
        "filename" => $file->name,
        "contents" => fopen($file->filePath, "r")
      ];
    }, $files);

    // the job config must be among the uploaded files as well
    $filesToSubmit[] = [
      "name" => $this->jobConfigFileName,
      "filename" => $this->jobConfigFileName,
      "contents" => $jobConfig
    ];

    return $filesToSubmit;
  }

}
