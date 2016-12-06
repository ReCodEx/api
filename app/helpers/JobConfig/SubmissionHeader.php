<?php

namespace App\Helpers\JobConfig;
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
  /** Log bit key */
  const LOG_KEY = "log";

  /** @var array Additional data */
  private $data = [];
  /** @var JobId Job identification */
  private $jobId;
  /** @var string Fileserver url */
  private $fileCollector = "";
  /** @var string Programming language (no specific meaning yet, just better readability of config) */
  /** @var bool Logging of job evaluation */
  private $log = FALSE;
  /** @var array Available hardware groups */
  private $hardwareGroups = [];

  public function __construct() {
    $this->jobId = new JobId;
  }

  /**
   * Get textual representation of job identification.
   * @return string
   */
  public function getJobId(): string {
    return (string) $this->jobId;
  }

  /**
   * Set job identification alogside with its type.
   * @param string $jobId identification of job
   */
  public function setJobId(string $jobId) {
    $this->jobId->setJobId($jobId);
    return $this;
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
    return $this;
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
    return $this;
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
    return $this;
  }

  /**
   * Checks if log is on or off.
   * @return bool
   */
  public function getLog(): bool {
    return $this->log;
  }

  /**
   * Set logging on/off bit.
   * @param bool $log
   */
  public function setLog(bool $log) {
    $this->log = $log;
    return $this;
  }

  /**
   * Get hardware groups in this configuration.
   * @return array List of available hardware groups
   */
  public function getHardwareGroups(): array {
    return $this->hardwareGroups;
  }

  /**
   * Set available hardware groups in this configuration.
   * @param array $groups List of available hardware groups
   */
  public function setHardwareGroups(array $groups) {
    $this->hardwareGroups = $groups;
    return $this;
  }

  /**
   * Add new hardware group to list of available groups (if not present)
   * @param string $hwGroupId Hardware group identifier we want to be present in header
   */
  public function addHardwareGroup(string $hwGroupId) {
    if (!in_array($hwGroupId, $this->hardwareGroups)) {
      $this->hardwareGroups[] = $hwGroupId;
    }
    return $this;
  }

  /**
   * Remove hardware group from list of available groups (if present)
   * @param string $hwGroupId Hardware group identifier we want not to be present in header
   */
  public function removeHardwareGroup(string $hwGroupId) {
    if(($key = array_search($hwGroupId, $this->hardwareGroups)) !== FALSE) {
      unset($this->hardwareGroups[$key]);
    }
    return $this;
  }

  /**
   * Get additional data which was not parsed at construction.
   * @return array
   */
  public function getAdditionalData(): array {
    return $this->data;
  }

  public function setAdditionalData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::JOB_ID_KEY] = (string) $this->jobId;
    $data[self::FILE_COLLECTOR_KEY] = $this->fileCollector;
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
