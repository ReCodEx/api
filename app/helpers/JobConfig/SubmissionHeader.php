<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

class SubmissionHeader {

  /** @var array Raw data */
  private $data;

  /** @var JobId Job identification */
  private $jobId;

  /** @var string fileserver url */
  private $fileCollector = "";

  public function __construct(array $data) {
    $this->data = $data;

    if (!isset($data["job-id"])) {
      throw new JobConfigLoadingException("Submission header does not contain the 'job-id' field.");
    }

    $this->jobId = new JobId($data["job-id"]);
    if (array_key_exists("file-collector", $data)) {
      $this->fileCollector = $data["file-collector"];
    }
  }

  public function setJobId(string $type, string $id) {
    $this->jobId->setJobId($type, $id);
  }

  public function getJobId(): string {
    return (string) $this->jobId;
  }

  public function getId(): string {
    return $this->jobId->getId();
  }

  public function setId(string $id) {
    $this->jobId->setId($id);
  }

  public function getType(): string {
    return $this->jobId->getType();
  }

  public function setType(string $type) {
    $this->jobId->setType($type);
  }

  public function getFileCollector(): string {
    return $this->fileCollector;
  }

  public function setFileCollector(string $fileCollector) {
    $this->fileCollector = $fileCollector;
  }

  public function toArray() {
    $data = $this->data;
    $data['job-id'] = (string) $this->jobId;
    $data["file-collector"] = $this->fileCollector;
    return $data;
  }

  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
