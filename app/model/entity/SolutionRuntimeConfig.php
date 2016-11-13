<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
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
  protected $customName;

  /**
   * @ORM\ManyToOne(targetEntity="RuntimeEnvironment")
   */
  protected $runtimeEnvironment;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $jobConfigFilePath;

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
    $this->customName = $name;
    $this->runtimeEnvironment = $runtimeEnvironment;
    $this->jobConfigFilePath = $jobConfigFilePath;
    $this->solutionRuntimeConfig = $createdFrom;
    $this->createdAt = new \DateTime;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->customName,
      "createdAt" => $this->createdAt,
      "runtimeEnvironment" => $this->runtimeEnvironment
    ];
  }

}
