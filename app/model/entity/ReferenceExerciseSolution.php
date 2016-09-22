<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 */
class ReferenceExerciseSolution implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="Exercise")
   * @ORM\JoinColumn(name="exercise_id", referencedColumnName="id")
   */
  protected $exercise;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $uploadedAt;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\Column(type="string")
   */
  protected $sourceCodeFilePath;


  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "uploadedAt" => $this->uploadedAt->getTimestamp(),
      "description" => $this->description
    ];
  }


  public function __construct(Exercise $exercise, string $sourceCodeFilePath, \DateTime $uploadedAt, string $description) {
    $this->exercise = $exercise;
    $this->sourceCodeFilePath = $sourceCodeFilePath;
    $this->uploadedAt = $uploadedAt;
    $this->description = $description;
  }
}