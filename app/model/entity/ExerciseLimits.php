<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="exercise_runtime_hwgroup_key", columns={"exercise_id", "runtime_config_id", "hardware_group_id"})})
 * @method string getId()
 * @method string getLimits()
 */
class ExerciseLimits implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="text")
   */
  protected $limits;

  /**
   * @ORM\ManyToOne(targetEntity="Exercise", inversedBy="exercises")
   */
  protected $exercise;

  /**
   * @ORM\ManyToOne(targetEntity="RuntimeConfig")
   */
  protected $runtimeConfig;

  /**
   * @ORM\ManyToOne(targetEntity="HardwareGroup")
   */
  protected $hardwareGroup;

  /**
   * Constructor
   */
  public function __construct(Exercise $exercise, RuntimeConfig $runtimeConfig,
      HardwareGroup $hardwareGroup, string $limits) {
    $this->exercise = $exercise;
    $this->runtimeConfig = $runtimeConfig;
    $this->hardwareGroup = $hardwareGroup;
    $this->limits = $limits;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "exerciseId" => $this->exercise->getId(),
      "runtimeConfigId" => $this->runtimeConfig->getId(),
      "hardwareGroupId" => $this->hardwareGroup->getId(),
      "limits" => $this->limits
    ];
  }

}
