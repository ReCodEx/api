<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

class SubmissionHeader {

  /** @var array Raw data */
  private $data;

  /** @var string ID of job */
  private $jobId;

  /** @var string fileserver url */
  private $fileCollector = "";

  public function __construct(array $data) {
    $this->data = $data;

    if (!isset($data["job-id"])) {
      throw new JobConfigLoadingException("Submission header does not contain the 'job-id' field.");
    }

    $this->jobId = $data["job-id"];
    if (array_key_exists("file-collector", $data)) {
      $this->fileCollector = $data["file-collector"];
    }
  }

  public function getJobId(): string {
    return $this->jobId;
  }

  public function setJobId(string $jobId) {
    $this->jobId = $jobId;
  }

  public function getFileCollector(): string {
    return $this->fileCollector;
  }

  public function setFileCollector(string $fileCollector) {
    $this->fileCollector = $fileCollector;
  }

  public function toArray() {
    $data = $this->data;
    $data["job-id"] = $this->jobId;
    $data["file-collector"] = $this->fileCollector;
    return $data;
  }

  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
