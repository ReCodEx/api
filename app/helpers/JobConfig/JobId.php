<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;


/**
 *
 */
class JobId {
  /** Separator used in ID */
  const SEPARATOR = "_";
  /** Allowed types which can be used in ID */
  const ALLOWED_TYPES = array("student", "reference");

  /** @var string Type of the job */
  private $id;
  /** @var string Type of the job */
  private $type;

  /**
   *
   * @param string $type
   * @throws JobConfigLoadingException
   */
  private function checkTypeValidity(string $type) {
    if (!in_array($type, self::ALLOWED_TYPES)) {
      throw new JobConfigLoadingException("Job id contains unknown type '" . $type . "'.");
    }
  }

  /**
   *
   * @param string $jobId
   */
  public function __construct(string $jobId) {
    if (!strpos($jobId, self::SEPARATOR)) {
      $this->id = $jobId;
      $this->type = "student";
    } else {
      list($this->type, $this->id) = explode(self::SEPARATOR, $jobId, 2);
      $this->checkTypeValidity($this->type);
    }
  }

  /**
   *
   * @param string $type
   * @param string $id
   */
  public function setJobId(string $type, string $id) {
    $this->setType($type);
    $this->setId($id);
  }

  /**
   *
   * @return string
   */
  public function getJobId(): string {
    return $this->type . self::SEPARATOR . $this->id;
  }

  /**
   *
   * @return string
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   *
   * @param string $id
   */
  public function setId(string $id) {
    $this->id = $id;
  }

  /**
   *
   * @return string
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   *
   * @param string $type
   */
  public function setType(string $type) {
    $this->checkTypeValidity($type);
    $this->type = $type;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return $this->getJobId();
  }

}
