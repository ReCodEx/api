<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;


/**
 * Header which represents and holds information about job submission.
 */
class SubmissionHeader {
  /** Job identification key */
  const JOB_ID_KEY = "job-id";
  /** File collector key */
  const FILE_COLLECTOR_KEY = "file-collector";
  /** Language key */
  const HARDWARE_GROUPS_KEY = "hw-groups";
  /** Language key */
  const LANGUAGE_KEY = "language";
  /** Log bit key */
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
  /** @var array Available hardware groups */
  private $hardwareGroups = [];

  /**
   * Construct submission header from given structured data.
   * @param array $data Structured configuration
   * @throws JobConfigLoadingException In case of any parsing error
   */
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

    if (!isset($data[self::HARDWARE_GROUPS_KEY])) {
      throw new JobConfigLoadingException("Submission header does not contain the required '" . self::HARDWARE_GROUPS_KEY . "' field.");
    } else if (!is_array($data[self::HARDWARE_GROUPS_KEY])) {
      throw new JobConfigLoadingException("Submission header field '" . self::HARDWARE_GROUPS_KEY . "' does not contain an array.");
    }
    $this->hardwareGroups = $data[self::HARDWARE_GROUPS_KEY];
    unset($data[self::HARDWARE_GROUPS_KEY]);

    if (isset($data[self::LOG_KEY])) {
      $this->log = filter_var($data[self::LOG_KEY], FILTER_VALIDATE_BOOLEAN);
      unset($data[self::LOG_KEY]);
    }

    $this->data = $data;
  }

  /**
   * Set job identification alogside with its type.
   * @param string $type type of job
   * @param string $id identification of job
   */
  public function setJobId(string $type, string $id) {
    $this->jobId->setJobId($type, $id);
  }

  /**
   * Get textual representation of job identification.
   * @return string
   */
  public function getJobId(): string {
    return (string) $this->jobId;
  }

  /**
   * Get job identification without type.
   * @return string
   */
  public function getId(): string {
    return $this->jobId->getId();
  }

  /**
   * Set job identification without type.
   * @param string $id
   */
  public function setId(string $id) {
    $this->jobId->setId($id);
  }

  /**
   * Get job type which is coded into job id.
   * @return string
   */
  public function getType(): string {
    return $this->jobId->getType();
  }

  /**
   * Set type of this job.
   * @param string $type
   */
  public function setType(string $type) {
    $this->jobId->setType($type);
  }

  /**
   * Get fileserver URL.
   * @return string
   */
  public function getFileCollector(): string {
    return $this->fileCollector;
  }

  /**
   * Set fileserver URL.
   * @param string $fileCollector
   */
  public function setFileCollector(string $fileCollector) {
    $this->fileCollector = $fileCollector;
  }

  /**
   * Set language of this job.
   * @param string $language
   */
  public function setLanguage(string $language) {
    $this->language = $language;
  }

  /**
   * Gets language of this job.
   * @return string
   */
  public function getLanguage(): string {
    return $this->language;
  }

  /**
   * Set logging on/off bit.
   * @param bool $log
   */
  public function setLog(bool $log) {
    $this->log = $log;
  }

  /**
   * Checks if log is on or off.
   * @return bool
   */
  public function getLog(): bool {
    return $this->log;
  }

  /**
   * Set available hardware groups.
   * @param bool $log
   */
  public function setHardwareGroups(array $groups) {
    $this->hardwareGroups = $groups;
  }

  /**
   * Checks if log is on or off.
   * @return bool
   */
  public function getHardwareGroups(): array {
    return $this->hardwareGroups;
  }

  /**
   * Get additional data which was not parsed at construction.
   * @return array
   */
  public function getAdditionalData(): array {
    return $this->data;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::JOB_ID_KEY] = (string) $this->jobId;
    $data[self::FILE_COLLECTOR_KEY] = $this->fileCollector;
    $data[self::LANGUAGE_KEY] = $this->language;
    $data[self::LOG_KEY] = $this->log ? "true" : "false";
    $data[self::HARDWARE_GROUPS_KEY] = $this->hardwareGroups;
    return $data;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

}
