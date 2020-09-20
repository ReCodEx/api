<?php

namespace App\Model\Entity;

use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\IImmutableFile;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 */
class AttachmentFile extends UploadedFile implements JsonSerializable
{
    /**
     * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="attachmentFiles")
     */
    protected $exercises;

    /**
     * @return Collection
     */
    public function getExercises()
    {
        return $this->exercises->filter(
            function (Exercise $exercise) {
                return $exercise->getDeletedAt() === null;
            }
        );
    }

    /**
     * @ORM\ManyToMany(targetEntity="Assignment", mappedBy="attachmentFiles")
     */
    protected $assignments;

    /**
     * @return Collection
     */
    public function getAssignments()
    {
        return $this->assignments->filter(
            function (Assignment $assignment) {
                return $assignment->getDeletedAt() === null;
            }
        );
    }


    /**
     * AttachmentFile constructor.
     * @param $name
     * @param DateTime $uploadedAt
     * @param $fileSize
     * @param User|null $user
     * @param Exercise $exercise
     */
    public function __construct($name, DateTime $uploadedAt, $fileSize, ?User $user, Exercise $exercise)
    {
        parent::__construct($name, $uploadedAt, $fileSize, $user, true);
        $this->exercises = new ArrayCollection();
        $this->assignments = new ArrayCollection();

        $this->exercises->add($exercise);
        $exercise->addAttachmentFile($this);
    }

    public static function fromUploadedFile(UploadedFile $file, Exercise $exercise)
    {
        return new self(
            $file->getName(),
            $file->getUploadedAt(),
            $file->getFileSize(),
            $file->getUser(),
            $exercise
        );
    }

    public function jsonSerialize()
    {
        $result = parent::jsonSerialize();
        return $result;
    }
   
    public function getFile(FileStorageManager $manager): ?IImmutableFile
    {
        return $manager->getAttachmentFile($this);
    }
}
