<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @method ArrayCollection getExercises()
 * @method ArrayCollection getAssignments()
 * @ORM\Entity
 */
class AttachmentFile extends UploadedFile implements JsonSerializable
{
  /**
   * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="attachmentFiles")
   */
  protected $exercises;

  /**
   * @ORM\ManyToMany(targetEntity="Assignment", mappedBy="attachmentFiles")
   */
  protected $assignments;

  public function __construct($name, DateTime $uploadedAt, $fileSize, $filePath, User $user, Exercise $exercise)
  {
    parent::__construct($name, $uploadedAt, $fileSize, $user, $filePath, TRUE);
    $this->exercises = new ArrayCollection;
    $this->assignments = new ArrayCollection;

    $this->exercises->add($exercise);
    $exercise->addAttachmentFile($this);
  }

  public static function fromUploadedFile(UploadedFile $file, Exercise $exercise)
  {
    return new self(
      $file->getName(),
      $file->getUploadedAt(),
      $file->getFileSize(),
      $file->getLocalFilePath(),
      $file->getUser(),
      $exercise
    );
  }

  public function jsonSerialize() {
    $result = parent::jsonSerialize();
    return $result;
  }
}
