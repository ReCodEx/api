<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;


/**
 * Job identification holder structure.
 */
class JobId {
  /** Separator used in ID */
  const SEPARATOR = "_";
  /** Allowed types which can be used in ID */
  const ALLOWED_TYPES = array("student", "reference");

  /** @var string Identification of the job */
  private $id;
  /** @var string Type of the job */
  private $type;

  /**
   * Check if type of identification is the right one.
   * @param string $type
   * @throws JobConfigLoadingException
   */
  private function checkTypeValidity(string $type) {
    if (!in_array($type, self::ALLOWED_TYPES)) {
      throw new JobConfigLoadingException("Job id contains unknown type '" . $type . "'.");
    }
  }

  private function parse($jobId) {
    if (!strpos($jobId, self::SEPARATOR)) {
      $this->id = $jobId;
      $this->type = "student";
    } else {
      list($this->type, $this->id) = explode(self::SEPARATOR, $jobId, 2);
      $this->checkTypeValidity($this->type);
    }
  }

  /**
   * Create job ID from given textual description.
   * @param string $jobId
   */
  public function __construct(string $jobId = "") {
    $this->parse($jobId);
  }

  /**
   * Get textual description of type and identification.
   * @return string
   */
  public function getJobId(): string {
    return $this->type . self::SEPARATOR . $this->id;
  }

  /**
   * Set type and identification of a job.
   * @param string $jobId Identification of a job
   */
  public function setJobId(string $jobId) {
    $this->parse($jobId);
    return $this;
  }

  /**
   * Get only identification.
   * @return string
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Set only identification.
   * @param string $id
   */
  public function setId(string $id) {
    $this->id = $id;
    return $this;
  }

  /**
   * Get type of job.
   * @return string
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Set type of job.
   * @param string $type
   */
  public function setType(string $type) {
    $this->checkTypeValidity($type);
    $this->type = $type;
    return $this;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return $this->getJobId();
  }

}
