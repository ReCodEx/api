<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

class JobId {

  const SEPARATOR = "_";
  const ALLOWED_TYPES = array("student", "reference");

  /** @var string Type of the job */
  private $id;

  /** @var string Type of the job */
  private $type;

  private function checkTypeValidity(string $type) {
    if (!in_array($type, self::ALLOWED_TYPES)) {
      throw new JobConfigLoadingException("Job id contains unknown type '" . $type . "'.");
    }
  }

  public function __construct(string $jobId) {
    if (!strpos($jobId, self::SEPARATOR)) {
      $this->id = $jobId;
      $this->type = "student";
    } else {
      list($this->type, $this->id) = explode(self::SEPARATOR, $jobId, 2);
      $this->checkTypeValidity($this->type);
    }
  }

  public function setJobId(string $type, string $id) {
    $this->setType($type);
    $this->setId($id);
  }

  public function getJobId(): string {
    return $this->type . self::SEPARATOR . $this->id;
  }

  public function getId(): string {
    return $this->id;
  }

  public function setId(string $id) {
    $this->id = $id;
  }

  public function getType(): string {
    return $this->type;
  }

  public function setType(string $type) {
    $this->checkTypeValidity($type);
    $this->type = $type;
  }

  public function __toString() {
    return $this->getJobId();
  }

}
