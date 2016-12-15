<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

use App\Helpers\JobConfig;
use App\Exceptions\MalformedJobConfigException;
use App\Exceptions\JobConfigLoadingException;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getJobConfigFilePath()
 * @method RuntimeEnvironment getRuntimeEnvironment()
 */
class SolutionRuntimeConfig implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\ManyToOne(targetEntity="RuntimeEnvironment")
   */
  protected $runtimeEnvironment;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $jobConfigFilePath;

  public function getJobConfigFileContent() {
    return @file_get_contents($this->jobConfigFilePath);
  }

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * Created from.
   * @ORM\ManyToOne(targetEntity="SolutionRuntimeConfig")
   */
  protected $solutionRuntimeConfig;

  public function __construct(
    string $name,
    RuntimeEnvironment $runtimeEnvironment,
    string $jobConfigFilePath,
    $createdFrom = NULL
  ) {
    $this->name = $name;
    $this->runtimeEnvironment = $runtimeEnvironment;
    $this->jobConfigFilePath = $jobConfigFilePath;
    $this->solutionRuntimeConfig = $createdFrom;
    $this->createdAt = new \DateTime;
  }

  /**
   * Check the job configurations of all the files.
   * @return array All the runtime environments have valid job configs.
   */
  public function isValid() {
    try {
      $jobConfigStorage = new JobConfig\Storage;
      $jobConfigStorage->parseJobConfig($this->getJobConfigFileContent());
    } catch (MalformedJobConfigException $e) {
      return FALSE;
    } catch (JobConfigLoadingException $e) {
      return FALSE;
    }

    return TRUE;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "createdAt" => $this->createdAt->getTimestamp(),
      "jobConfig" => $this->getJobConfigFileContent(),
      "isValid" => $this->isValid(),
      "runtimeEnvironmentId" => $this->runtimeEnvironment->getId(),
      "createdFrom" => $this->solutionRuntimeConfig ? $this->solutionRuntimeConfig->id : ""
    ];
  }

}
