<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 */
class ExerciseFile extends UploadedFile implements JsonSerializable
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
   * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="supplementaryEvaluationFiles")
   */
  protected $exercises;

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

    $this->exercises = new ArrayCollection;
    $this->exercises->add($exercise);
    $exercise->addSupplementaryEvaluationFile($this);
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
    return $result;
  }
}
