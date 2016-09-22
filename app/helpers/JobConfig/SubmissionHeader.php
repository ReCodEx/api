<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

class SubmissionHeader {

  const TYPE_UNSPECIFIED = "recodex-unspecified";
  const SEPARATOR = "/";

  /** @var array Raw data */
  private $data;

  /** @var string Type of the job */
  private $type;

  /** @var string ID of job */
  private $jobId;

  public function __construct(array $data) {
    $this->data = $data;

    if (!isset($data["job-id"])) {
      throw new JobConfigLoadingException("Submission header does not contain the 'job-id' field.");
    }

    $jobId = $data["job-id"];
    if (!strpos($jobId, self::SEPARATOR)) {
      $this->jobId = $jobId;
      $this->type = self::TYPE_UNSPECIFIED;
    } else {
      list($this->type, $this->jobId) = explode(self::SEPARATOR, $jobId, 2);
    }
  }

  public function getJobId(): string {
    return $this->jobId;
  }

  public function setJobId(string $jobId) {
    $this->jobId = $jobId;
  }

  public function getJobType(): string {
    return $this->type;
  }

  public function setJobType(string $type) {
    if (strpos($type, self::SEPARATOR) !== FALSE) {
      throw new JobConfigLoadingException("Submission type cannot contain the '" . self::SEPARATOR . "' character.");
    }
    $this->type = $type;
  }

  public function toArray() {
    $data = $this->data;
    $data['job-id'] = $this->type . self::SEPARATOR . $this->jobId;
    return $data;
  }

  public function __toString() {
    return Yaml::dump($this->toArray());
  }

}
