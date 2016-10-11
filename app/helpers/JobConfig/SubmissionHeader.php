<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

class SubmissionHeader {
  const JOB_ID_KEY = "job-id";
  const FILE_COLLECTOR_KEY = "file-collector";
  const LANGUAGE_KEY = "language";
  const LOG_KEY = "log";

  /** @var array Additional data */
  private $data = [];

  /** @var JobId Job identification */
  private $jobId;

  /** @var string Fileserver url */
  private $fileCollector = "";

  /** @var string Programming language (no specific meaning yet, just better readability of config) */
  private $language = "";

  /** @var bool Logging of job evaluation */
  private $log = FALSE;

  public function __construct(array $data) {
    if (!isset($data[self::JOB_ID_KEY])) {
      throw new JobConfigLoadingException("Submission header does not contain the required '" . self::JOB_ID_KEY . "' field.");
    }
    $this->jobId = new JobId($data[self::JOB_ID_KEY]);
    unset($data[self::JOB_ID_KEY]);
    
    if (!isset($data[self::FILE_COLLECTOR_KEY])) {
      throw new JobConfigLoadingException("Submission header does not contain the required '" . self::FILE_COLLECTOR_KEY . "' field.");
    }
    $this->fileCollector = $data[self::FILE_COLLECTOR_KEY];
    unset($data[self::FILE_COLLECTOR_KEY]);

    if (!isset($data[self::LANGUAGE_KEY])) {
      throw new JobConfigLoadingException("Submission header does not contain the required '" . self::LANGUAGE_KEY . "' field.");
    }
    $this->language = $data[self::LANGUAGE_KEY];
    unset($data[self::LANGUAGE_KEY]);

    if (isset($data[self::LOG_KEY])) {
      $this->log = filter_var($data[self::LOG_KEY], FILTER_VALIDATE_BOOLEAN);
      unset($data[self::LOG_KEY]);
    }
    
    $this->data = $data;
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

  public function setLanguage(string $language) {
    $this->language = $language; 
  }

  public function getLanguage(): string {
    return $this->language;
  }

  public function setLog(bool $log) {
    $this->log = $log;
  }

  public function getLog(): bool {
    return $this->log;
  }

  public function getAdditionalData(): array {
    return $this->data;
  }

  public function toArray() {
    $data = $this->data;
    $data[self::JOB_ID_KEY] = (string) $this->jobId;
    $data[self::FILE_COLLECTOR_KEY] = $this->fileCollector;
    $data[self::LANGUAGE_KEY] = $this->language;
    $data[self::LOG_KEY] = $this->log ? "true" : "false";
    return $data;
  }

  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
