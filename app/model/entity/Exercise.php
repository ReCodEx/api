<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use App\Helpers\ExercisesConfig;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class Exercise implements IExercise
{
    use ExerciseData;
    use CreatableEntity;
    use UpdatableEntity;
    use DeletableEntity;
    use VersionableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $difficulty;

    /**
     * @ORM\ManyToMany(targetEntity="RuntimeEnvironment")
     */
    protected $runtimeEnvironments;

    /**
     * @ORM\ManyToOne(targetEntity="Exercise")
     * @ORM\JoinColumn(name="exercise_id", referencedColumnName="id")
     */
    protected $exercise;

    /**
     * @ORM\OneToMany(targetEntity="ReferenceExerciseSolution", mappedBy="exercise")
     */
    protected $referenceSolutions;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="exercises")
     */
    protected $author;

    /**
     * @ORM\ManyToMany(targetEntity="User")
     */
    protected $admins;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isPublic;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isLocked;

    /**
     * @ORM\Column(type="boolean", options={"default":0})
     */
    protected $isBroken = false;

    /**
     * @ORM\Column(type="text", length=65535)
     */
    protected $validationError;

    /**
     * @ORM\ManyToMany(targetEntity="Group", inversedBy="exercises")
     */
    protected $groups;

    /**
     * @ORM\OneToMany(targetEntity="Assignment", mappedBy="exercise")
     */
    protected $assignments;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="ExerciseTag", mappedBy="exercise",
     *                cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $tags;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $mergeJudgeLogs;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $archivedAt = null;

    /**
     * Constructor
     * @param int $version
     * @param string $difficulty
     * @param Collection $localizedTexts
     * @param Collection $runtimeEnvironments
     * @param Collection $hardwareGroups
     * @param Collection $exerciseFiles
     * @param Collection $attachmentFiles
     * @param Collection $exerciseLimits
     * @param Collection $exerciseEnvironmentConfigs
     * @param Collection $exerciseTests
     * @param Collection $groups
     * @param Exercise|null $exercise
     * @param ExerciseConfig|null $exerciseConfig
     * @param User $user
     * @param bool $isPublic
     * @param bool $isLocked
     * @param ExerciseScoreConfig $scoreConfig
     * @param string $configurationType
     * @throws Exception
     */
    private function __construct(
        $version,
        $difficulty,
        Collection $localizedTexts,
        Collection $runtimeEnvironments,
        Collection $hardwareGroups,
        Collection $exerciseFiles,
        Collection $attachmentFiles,
        Collection $exerciseLimits,
        Collection $exerciseEnvironmentConfigs,
        Collection $exerciseTests,
        Collection $groups,
        ?Exercise $exercise,
        ?ExerciseConfig $exerciseConfig,
        User $user,
        bool $isPublic = false,
        bool $isLocked = true,
        ?ExerciseScoreConfig $scoreConfig = null,
        string $configurationType = "simpleExerciseConfig",
        ?int $solutionFilesLimit = null,
        ?int $solutionSizeLimit = null
    ) {
        $this->version = $version;
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
        $this->localizedTexts = $localizedTexts;
        $this->difficulty = $difficulty;
        $this->runtimeEnvironments = $runtimeEnvironments;
        $this->exercise = $exercise;
        $this->author = $user;
        $this->admins = new ArrayCollection();
        $this->exerciseFiles = $exerciseFiles;
        $this->isPublic = $isPublic;
        $this->isLocked = $isLocked;
        $this->isBroken = false;
        $this->groups = $groups;
        $this->assignments = new ArrayCollection();
        $this->attachmentFiles = $attachmentFiles;
        $this->fileLinks = new ArrayCollection();
        $this->exerciseLimits = $exerciseLimits;
        $this->exerciseConfig = $exerciseConfig;
        $this->hardwareGroups = $hardwareGroups;
        $this->exerciseEnvironmentConfigs = $exerciseEnvironmentConfigs;
        $this->exerciseTests = $exerciseTests;
        $this->referenceSolutions = new ArrayCollection();
        $this->scoreConfig = $scoreConfig;
        $this->configurationType = $configurationType;
        $this->solutionFilesLimit = $solutionFilesLimit;
        $this->solutionSizeLimit = $solutionSizeLimit;
        $this->validationError = "";
        $this->tags = new ArrayCollection();
        $this->mergeJudgeLogs = true;
    }

    public static function create(
        User $user,
        Group $group,
        ?ExerciseScoreConfig $scoreConfig = null,
        ?ExercisesConfig $config = null
    ): Exercise {
        return new self(
            1,
            "",
            new ArrayCollection(),
            new ArrayCollection(),
            new ArrayCollection(),
            new ArrayCollection(),
            new ArrayCollection(),
            new ArrayCollection(),
            new ArrayCollection(),
            new ArrayCollection(),
            new ArrayCollection([$group]),
            null,
            null,
            $user,
            false, // isPublic
            true, // isLocked
            $scoreConfig ?? new ExerciseScoreConfig(),
            "simpleExerciseConfig",
            $config ? $config->getSolutionFilesLimitDefault() : null,
            $config ? $config->getSolutionSizeLimitDefault() : null
        );
    }

    public static function forkFrom(Exercise $exercise, User $user, Group $group): Exercise
    {
        return new self(
            1,
            $exercise->difficulty,
            $exercise->localizedTexts,
            $exercise->runtimeEnvironments,
            $exercise->hardwareGroups,
            $exercise->exerciseFiles,
            $exercise->attachmentFiles,
            $exercise->exerciseLimits,
            $exercise->exerciseEnvironmentConfigs,
            $exercise->exerciseTests,
            new ArrayCollection([$group]),
            $exercise,
            $exercise->exerciseConfig,
            $user,
            $exercise->isPublic,
            true,
            $exercise->scoreConfig,
            $exercise->configurationType,
            $exercise->solutionFilesLimit,
            $exercise->solutionSizeLimit
        );

        // TODO handle file link duplication
    }

    public function setRuntimeEnvironments(Collection $runtimeEnvironments): void
    {
        $this->runtimeEnvironments = $runtimeEnvironments;
    }

    public function addRuntimeEnvironment(RuntimeEnvironment $runtimeEnvironment): void
    {
        $this->runtimeEnvironments->add($runtimeEnvironment);
    }

    public function removeRuntimeEnvironment(RuntimeEnvironment $runtimeEnvironment): void
    {
        $this->runtimeEnvironments->removeElement($runtimeEnvironment);
    }

    public function setExerciseTests(Collection $exerciseTests): void
    {
        $this->exerciseTests = $exerciseTests;
    }

    public function addExerciseTest(ExerciseTest $test): void
    {
        $this->exerciseTests->add($test);
    }

    public function removeExerciseTest(?ExerciseTest $test): void
    {
        $this->exerciseTests->removeElement($test);
    }

    public function addHardwareGroup(HardwareGroup $hardwareGroup): void
    {
        $this->hardwareGroups->add($hardwareGroup);
    }

    public function removeHardwareGroup(?HardwareGroup $hardwareGroup): void
    {
        $this->hardwareGroups->removeElement($hardwareGroup);
    }

    public function addExerciseLimits(ExerciseLimits $exerciseLimits): void
    {
        $this->exerciseLimits->add($exerciseLimits);
    }

    public function removeExerciseLimits(?ExerciseLimits $exerciseLimits): void
    {
        $this->exerciseLimits->removeElement($exerciseLimits);
    }

    public function addExerciseEnvironmentConfig(ExerciseEnvironmentConfig $exerciseEnvironmentConfig): void
    {
        $this->exerciseEnvironmentConfigs->add($exerciseEnvironmentConfig);
    }

    public function removeExerciseEnvironmentConfig(?ExerciseEnvironmentConfig $runtimeConfig): void
    {
        $this->exerciseEnvironmentConfigs->removeElement($runtimeConfig);
    }

    /**
     * Get a list of admin IDs.
     * @return string[]
     */
    public function getAdminsIds(): array
    {
        return $this->getAdmins()->map(function (User $admin) {
            return $admin->getId();
        })->getValues();
    }

    /**
     * Get IDs of all assigned groups.
     * @return string[]
     */
    public function getGroupsIds()
    {
        return $this->getGroups()->map(
            function (Group $group) {
                return $group->getId();
            }
        )->getValues();
    }

    public function clearExerciseLimits()
    {
        $this->exerciseLimits->clear();
    }

    /**
     * Is there at least one text or external link in at least one localization?
     * @return bool
     */
    public function hasNonemptyLocalizedTexts(): bool
    {
        foreach ($this->getLocalizedTexts() as $localizedText) {
            /** @var LocalizedExercise $localizedText */
            if (!$localizedText->isEmpty()) {
                return true;
            }
        }
        return false;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getForkedFrom(): ?Exercise
    {
        return $this->exercise && $this->exercise->isDeleted() ? null : $this->exercise;
    }

    public function isAuthor(User $user): bool
    {
        return $this->author && $this->author->getId() === $user->getId();
    }

    public function getAuthor(): ?User
    {
        return $this->author->isDeleted() ? null : $this->author;
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    public function getAdmins(): ReadableCollection
    {
        return $this->admins->filter(
            function (User $user) {
                return $user->getDeletedAt() === null;
            }
        );
    }

    public function addAdmin(User $admin): void
    {
        if ($admin->getId() !== $this->author->getId()) {
            $this->admins->add($admin);
        }
    }

    public function removeAdmin(User $admin): void
    {
        $this->admins->removeElement($admin);
    }

    public function getExerciseLimits(): Collection
    {
        return $this->exerciseLimits;
    }

    public function getExerciseEnvironmentConfigs(): Collection
    {
        return $this->exerciseEnvironmentConfigs;
    }

    public function getDifficulty(): string
    {
        return $this->difficulty;
    }

    public function setDifficulty(string $difficulty): void
    {
        $this->difficulty = $difficulty;
    }

    public function setScoreConfig(ExerciseScoreConfig $scoreConfig): void
    {
        $this->scoreConfig = $scoreConfig;
    }

    public function getReferenceSolutions(?int $minimalVisibility = null): Collection
    {
        if ($minimalVisibility !== null) {
            return $this->referenceSolutions->filter(
                function (ReferenceExerciseSolution $rs) use ($minimalVisibility) {
                    return $rs->getVisibility() >= $minimalVisibility;
                }
            );
        } else {
            return $this->referenceSolutions;
        }
    }

    public function getExerciseTests(): Collection
    {
        return $this->exerciseTests;
    }

    public function getGroups(): Collection
    {
        return $this->groups->filter(
            function (Group $group) {
                return !$group->isDeleted();
            }
        );
    }

    public function getAssignments(): Collection
    {
        return $this->assignments->filter(
            function (Assignment $assignment) {
                return !$assignment->isDeleted();
            }
        );
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(ExerciseTag $tag): void
    {
        $this->tags->add($tag);
    }

    public function removeTag(ExerciseTag $tag): void
    {
        $this->tags->removeElement($tag);
    }

    public function isPublic()
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): void
    {
        $this->isPublic = $isPublic;
    }

    public function isLocked()
    {
        return $this->isLocked;
    }

    public function setLocked($value = true)
    {
        $this->isLocked = $value;
    }

    public function isBroken()
    {
        return $this->isBroken;
    }

    public function getValidationError(): ?string
    {
        if ($this->isBroken) {
            return $this->validationError;
        }

        return null;
    }

    public function setBroken(string $message): void
    {
        $this->isBroken = true;
        $this->validationError = $message;
    }

    public function setNotBroken(): void
    {
        $this->isBroken = false;
    }

    public function setExerciseConfig(ExerciseConfig $exerciseConfig): void
    {
        $this->exerciseConfig = $exerciseConfig;
    }

    public function setConfigurationType(string $configurationType): void
    {
        $this->configurationType = $configurationType;
    }

    public function addGroup(Group $group): void
    {
        $this->groups->add($group);
    }

    public function removeGroup(Group $group): void
    {
        $this->groups->removeElement($group);
    }

    public function getMergeJudgeLogs(): bool
    {
        return $this->mergeJudgeLogs;
    }

    public function setMergeJudgeLogs(bool $value): void
    {
        $this->mergeJudgeLogs = $value;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function getArchivedAt(): ?DateTime
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?DateTime $archivedAt = null): void
    {
        $this->archivedAt = $archivedAt;
    }
}
