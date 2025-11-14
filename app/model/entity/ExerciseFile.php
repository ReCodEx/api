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
class ExerciseFile extends UploadedFile implements JsonSerializable
{
    /**
     * Identifier used to store/retrieve the file in/from the storage.
     * @ORM\Column(type="string")
     */
    protected $hashName;

    /**
     * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="exerciseFiles")
     * @var Collection<Exercise>
     */
    protected $exercises;

    /**
     * @return Collection<Exercise>
     */
    public function getExercises(): Collection
    {
        return $this->exercises->filter(
            function (Exercise $exercise) {
                return $exercise->getDeletedAt() === null;
            }
        );
    }

    /**
     * @ORM\ManyToMany(targetEntity="Assignment", mappedBy="exerciseFiles")
     * @var Collection<Assignment>
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
     * @ORM\ManyToMany(targetEntity="Pipeline", mappedBy="exerciseFiles")
     * @var Collection<Pipeline>
     */
    protected $pipelines;

    /**
     * @ORM\OneToMany(targetEntity="ExerciseFileLink", mappedBy="exerciseFile")
     * @var Collection<ExerciseFileLink>
     */
    protected $links;


    /**
     * ExerciseFile constructor.
     * @param string $name
     * @param DateTime $uploadedAt
     * @param int $fileSize
     * @param string $hashName
     * @param User|null $user
     * @param Exercise|null $exercise
     * @param Pipeline|null $pipeline
     */
    public function __construct(
        string $name,
        DateTime $uploadedAt,
        int $fileSize,
        string $hashName,
        ?User $user,
        ?Exercise $exercise = null,
        ?Pipeline $pipeline = null
    ) {
        parent::__construct($name, $uploadedAt, $fileSize, $user);
        $this->hashName = $hashName;

        $this->exercises = new ArrayCollection();
        $this->assignments = new ArrayCollection();
        $this->pipelines = new ArrayCollection();
        $this->links = new ArrayCollection();

        if ($exercise) {
            $this->exercises->add($exercise);
            $exercise->addExerciseFile($this);
        }

        if ($pipeline) {
            $this->pipelines->add($pipeline);
            $pipeline->addExerciseFile($this);
        }
    }

    public static function fromUploadedFileAndExercise(UploadedFile $file, Exercise $exercise, string $hashName)
    {
        return new self(
            $file->getName(),
            $file->getUploadedAt(),
            $file->getFileSize(),
            $hashName,
            $file->getUser(),
            $exercise,
            null
        );
    }

    public static function fromUploadedFileAndPipeline(UploadedFile $file, Pipeline $pipeline, string $hashName)
    {
        return new self(
            $file->getName(),
            $file->getUploadedAt(),
            $file->getFileSize(),
            $hashName,
            $file->getUser(),
            null,
            $pipeline
        );
    }

    public function jsonSerialize(): mixed
    {
        $result = parent::jsonSerialize();
        $result["hashName"] = $this->hashName;
        return $result;
    }

    public function getFile(FileStorageManager $manager): ?IImmutableFile
    {
        return $manager->getExerciseFileByHash($this->getHashName());
    }

    /*
     * Accessors
     */

    public function getHashName(): string
    {
        return $this->hashName;
    }
}
