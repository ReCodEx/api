<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class ExerciseFile extends UploadedFile
{
  /**
   * @ORM\Column(type="string")
   */
  protected $hashName;

  /**
   * @ORM\Column(type="string")
   */
  protected $fileServerPath;

  /**
   * @ORM\ManyToOne(targetEntity="Exercise", inversedBy="supplementaryFiles")
   */
  protected $exercise;

  public function __construct(
    string $name,
    DateTime $uploadedAt,
    int $fileSize,
    string $hashName,
    string $fileServerPath,
    User $user,
    Exercise $exercise
  ) {
    parent::__construct($name, $uploadedAt, $fileSize, $user);
    $this->hashName = $hashName;
    $this->fileServerPath = $fileServerPath;
    $this->exercise = $exercise;
    $exercise->addSupplementaryFile($this);
  }

  public static function fromUploadedFile(UploadedFile $file, Exercise $exercise, string $hashName, string $fileServerPath) {
    return new self(
      $file->getName(),
      $file->getUploadedAt(),
      $file->getFileSize(),
      $hashName,
      $fileServerPath,
      $file->getUser(),
      $exercise
    );
  }

  public function jsonSerialize() {
    $result = parent::jsonSerialize();
    $result["hashName"] = $this->hashName;
    $result["exerciseId"] = $this->exercise->getId();
    return $result;
  }
}
