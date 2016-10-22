<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class AssignmentRuntimeConfig implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="RuntimeEnvironment")
   */
  protected $runtimeEnvironment;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $jobConfigFilePath;


  public function __construct(
    RuntimeEnvironment $runtimeEnvironment,
    $jobConfigFilePath
  ) {
    $this->runtimeEnvironment = $runtimeEnvironment;
    $this->jobConfigFilePath = $jobConfigFilePath;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "runtimeEnvironmentId" => $this->runtimeEnvironment->getId(),
      "jobConfigFilePath" => $this->jobConfigFilePath
    ];
  }

}
