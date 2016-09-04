<?php

namespace App\Helpers;

use Doctrine\Common\Collections\ArrayCollection;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

use App\Exception\SubmissionFailedException;
use App\Exception\SubmissionEvaluationFailedException;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use ZipArchive;

/**
 * @author  Šimon Rozsíval <simon@rozsival.com>
 */
class FileServerProxy {

  /** @var string */
  private $remoteServerAddress;

  /** @var string */
  private $jobConfigFileName;

  /** @var Client */
  private $client;

  public function __construct(array $config) {
    $this->remoteServerAddress = $config["address"];
    $this->jobConfigFileName = $config["jobConfigFileName"];
    $this->client = new Client([
      "base_uri" => $config["address"],
      "auth" => [
        $config["username"],
        $config["password"]
      ]
    ]);
  }

  /**
   * Downloads the contents of a file at the given URL
   * @param   string $url   URL of the file
   * @return  string        Contents of the file
   */
  public function downloadResults(string $url) {
    try {
      $response = $this->client->request("GET", $url);
    } catch (ClientException $e) {
      throw new SubmissionEvaluationFailedException("Results are not available.");
    }
    $zip = $response->getBody();
    return $this->getResultYmlContent($zip);
  }

  /**
   * Extracts the contents of the downloaded ZIP file
   * @param   string $zipFileContent    Content of the zip file
   * @return  string
   */
  private function getResultYmlContent($zipFileContent) {
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

  /**
   * @param string   $submissionId
   * @param string[] $files
   * @return mixed
   * @throws SubmissionFailedException
   */
  public function sendFiles(string $submissionId, string $jobConfig, array $files) {
    $filesToSubmit = $this->prepareFiles($jobConfig, $files);

    try {
      $response = $this->client->request(
        "POST",
        "/submissions/$submissionId",
        [ "multipart" => $filesToSubmit ]
      );

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
