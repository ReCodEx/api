<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 *
 * @method string getHashName()
 * @method string getFileServerPath()
 */
class SupplementaryExerciseFile extends UploadedFile implements JsonSerializable
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

  /**
   * @return Collection
   */
  public function getExercises() {
    return $this->exercises->filter(function (Exercise $exercise) {
      return $exercise->getDeletedAt() === null;
    });
  }

  /**
   * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="supplementaryEvaluationFiles")
   */
  protected $assignments;

  /**
   * @return Collection
   */
  public function getAssignments() {
    return $this->assignments->filter(function (Assignment $assignment) {
      return $assignment->getDeletedAt() === null;
    });
  }

  /**
   * @ORM\ManyToMany(targetEntity="Pipeline", mappedBy="supplementaryEvaluationFiles")
   */
  protected $pipelines;


  /**
   * SupplementaryExerciseFile constructor.
   * @param string $name
   * @param DateTime $uploadedAt
   * @param int $fileSize
   * @param string $hashName
   * @param string $fileServerPath
   * @param User $user
   * @param Exercise|null $exercise
   * @param Pipeline|null $pipeline
   */
  public function __construct(
    string $name,
    DateTime $uploadedAt,
    int $fileSize,
    string $hashName,
    string $fileServerPath,
    User $user,
    Exercise $exercise = null,
    Pipeline $pipeline = null
  ) {
    parent::__construct($name, $uploadedAt, $fileSize, $user);
    $this->hashName = $hashName;
    $this->fileServerPath = $fileServerPath;

    $this->exercises = new ArrayCollection;
    $this->assignments = new ArrayCollection;
    $this->pipelines = new ArrayCollection;

    if ($exercise) {
      $this->exercises->add($exercise);
      $exercise->addSupplementaryEvaluationFile($this);
    }

    if ($pipeline) {
      $this->pipelines->add($pipeline);
      $pipeline->addSupplementaryEvaluationFile($this);
    }
  }

  public static function fromUploadedFileAndExercise(UploadedFile $file, Exercise $exercise, string $hashName, string $fileServerPath) {
    return new self(
      $file->getName(),
      $file->getUploadedAt(),
      $file->getFileSize(),
      $hashName,
      $fileServerPath,
      $file->getUser(),
      $exercise,
      null
    );
  }

  public static function fromUploadedFileAndPipeline(UploadedFile $file, Pipeline $pipeline, string $hashName, string $fileServerPath) {
    return new self(
      $file->getName(),
      $file->getUploadedAt(),
      $file->getFileSize(),
      $hashName,
      $fileServerPath,
      $file->getUser(),
      null,
      $pipeline
    );
  }

  public function jsonSerialize() {
    $result = parent::jsonSerialize();
    $result["hashName"] = $this->hashName;
    return $result;
  }
}
