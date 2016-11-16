<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class SupplementaryFile implements JsonSerializable
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
   * @ORM\Column(type="string")
   */
  protected $hashName;

  /**
   * @ORM\Column(type="string")
   */
  protected $fileServerPath;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $uploadedAt;

  /**
   * @ORM\Column(type="integer")
   */
  protected $fileSize;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $user;

  /**
   * @ORM\ManyToOne(targetEntity="Exercise", inversedBy="supplementaryFiles")
   */
  protected $exercise;

  public function __construct(string $name, string $hashName, string $fileServerPath, int $fileSize, User $user, Exercise $exercise) {
    $this->name = $name;
    $this->hashName = $hashName;
    $this->fileServerPath = $fileServerPath;
    $this->uploadedAt = new DateTime;
    $this->fileSize = $fileSize;
    $this->user = $user;
    $this->exercise = $exercise;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "hashName" => $this->hashName,
      "size" => $this->fileSize,
      "uploadedAt" => $this->uploadedAt->getTimestamp(),
      "userId" => $this->user->getId(),
      "exerciseId" => $this->exercise->getId()
    ];
  }
}
