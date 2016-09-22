<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

class JobId {

  const TYPE_UNSPECIFIED = "recodex-unspecified";
  const SEPARATOR = "_";

  /** @var string Type of the job */
  private $id;

  /** @var string Type of the job */
  private $type;

  public function __construct(string $jobId) {
    if (!strpos($jobId, self::SEPARATOR)) {
      $this->id = $jobId;
      $this->type = self::TYPE_UNSPECIFIED;
    } else {
      list($this->type, $this->id) = explode(self::SEPARATOR, $jobId, 2);
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
    if (strpos($type, self::SEPARATOR) !== FALSE) {
      throw new JobConfigLoadingException("Submission type cannot contain the '" . self::SEPARATOR . "' character.");
    }
    $this->type = $type;
  }

  public function __toString() {
    return $this->getJobId();
  }

}
