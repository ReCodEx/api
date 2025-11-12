<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidStateException;
use App\Helpers\Evaluation\IExercise;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Gedmo\Mapping\Annotation as Gedmo;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="first_deadline_idx", columns={"first_deadline"}),
 *                                @ORM\Index(name="second_deadline_idx", columns={"second_deadline"})})
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class Assignment extends AssignmentBase implements IExercise
{
    use ExerciseData;

    private function __construct(
        DateTime $firstDeadline,
        int $maxPointsBeforeFirstDeadline,
        Exercise $exercise,
        Group $group,
        bool $isPublic,
        int $submissionsCountLimit,
        bool $allowSecondDeadline,
        ?DateTime $secondDeadline = null,
        int $maxPointsBeforeSecondDeadline = 0,
        bool $canViewLimitRatios = false,
        bool $canViewMeasuredValues = false,
        bool $isBonus = false,
        $pointsPercentualThreshold = 0,
        ?DateTime $visibleFrom = null,
        bool $canViewJudgeStdout = false,
        bool $canViewJudgeStderr = false
    ) {
        $this->exercise = $exercise;
        $this->group = $group;
        $this->visibleFrom = $visibleFrom;
        $this->firstDeadline = $firstDeadline;
        $this->maxPointsBeforeFirstDeadline = $maxPointsBeforeFirstDeadline;
        $this->allowSecondDeadline = $allowSecondDeadline;
        $this->secondDeadline = $secondDeadline == null ? $firstDeadline : $secondDeadline;
        $this->maxPointsBeforeSecondDeadline = $maxPointsBeforeSecondDeadline;
        $this->assignmentSolutions = new ArrayCollection();
        $this->isPublic = $isPublic;
        $this->runtimeEnvironments = new ArrayCollection($exercise->getRuntimeEnvironments()->toArray());
        $this->disabledRuntimeEnvironments = new ArrayCollection();
        $this->hardwareGroups = new ArrayCollection($exercise->getHardwareGroups()->toArray());
        $this->exerciseTests = new ArrayCollection($exercise->getExerciseTests()->toArray());
        $this->exerciseLimits = new ArrayCollection($exercise->getExerciseLimits()->toArray());
        $this->exerciseEnvironmentConfigs = new ArrayCollection($exercise->getExerciseEnvironmentConfigs()->toArray());
        $this->exerciseConfig = $exercise->getExerciseConfig();
        $this->submissionsCountLimit = $submissionsCountLimit;
        $this->scoreConfig = $exercise->getScoreConfig();
        $this->localizedTexts = new ArrayCollection($exercise->getLocalizedTexts()->toArray());
        $this->localizedAssignments = new ArrayCollection();
        $this->canViewLimitRatios = $canViewLimitRatios;
        $this->canViewMeasuredValues = $canViewMeasuredValues;
        $this->canViewJudgeStdout = $canViewJudgeStdout;
        $this->canViewJudgeStderr = $canViewJudgeStderr;
        $this->mergeJudgeLogs = $exercise->getMergeJudgeLogs();
        $this->version = 1;
        $this->isBonus = $isBonus;
        $this->pointsPercentualThreshold = $pointsPercentualThreshold;
        $this->createdAt = new DateTime();
        $this->updatedAt = $this->createdAt;
        $this->syncedAt = $this->createdAt;
        $this->configurationType = $exercise->getConfigurationType();
        $this->exerciseFiles = $exercise->getExerciseFiles();
        $this->attachmentFiles = $exercise->getAttachmentFiles();
        $this->solutionFilesLimit = $exercise->getSolutionFilesLimit();
        $this->solutionSizeLimit = $exercise->getSolutionSizeLimit();
    }

    public static function assignToGroup(
        Exercise $exercise,
        Group $group,
        $isPublic = false,
        ?DateTime $firstDeadline = null
    ) {
        if ($exercise->getLocalizedTexts()->count() == 0) {
            throw new InvalidStateException("There are no localized descriptions of exercise");
        }

        if ($exercise->getRuntimeEnvironments()->count() == 0) {
            throw new InvalidStateException("There are no runtime environments in exercise");
        }

        $assignment = new self(
            $firstDeadline ?? new DateTime(),
            0,
            $exercise,
            $group,
            $isPublic,
            50,
            false
        );

        $group->addAssignment($assignment);

        return $assignment;
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\Column(type="float")
     */
    protected $pointsPercentualThreshold;

    /**
     * @ORM\ManyToMany(targetEntity="LocalizedAssignment", indexBy="locale")
     * @var Collection|Selectable
     */
    protected $localizedAssignments;

    public function getLocalizedAssignments(): Collection
    {
        return $this->localizedAssignments;
    }

    public function addLocalizedAssignment(LocalizedAssignment $localizedAssignment)
    {
        $this->localizedAssignments->add($localizedAssignment);
    }

    /**
     * Get localized assignment text based on given locale.
     * @param string $locale
     * @return LocalizedAssignment|null
     */
    public function getLocalizedAssignmentByLocale(string $locale): ?LocalizedAssignment
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
        $first = $this->localizedAssignments->matching($criteria)->first();
        return $first === false ? null : $first;
    }

    /**
     * @ORM\Column(type="smallint")
     */
    protected $submissionsCountLimit;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $visibleFrom;

    public function isVisibleToStudents()
    {
        // Is public unconditionally, or visible from date has already passed
        return $this->isPublic() && (!$this->visibleFrom || $this->visibleFrom <= (new DateTime()));
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $firstDeadline;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $allowSecondDeadline;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $secondDeadline;

    public function isAfterDeadline(?DateTime $now = null): bool
    {
        if ($now === null) {
            $now = new DateTime();
        }

        if ($this->allowSecondDeadline) {
            return $this->secondDeadline < $now;
        } else {
            return $this->firstDeadline < $now;
        }
    }

    public function isAfterFirstDeadline(?DateTime $now = null): bool
    {
        if ($now === null) {
            $now = new DateTime();
        }
        return $this->firstDeadline < $now;
    }

    /**
     * @ORM\Column(type="smallint")
     */
    protected $maxPointsBeforeFirstDeadline;

    /**
     * @ORM\Column(type="smallint")
     */
    protected $maxPointsBeforeSecondDeadline;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $maxPointsDeadlineInterpolation = false;

    public function getMaxPoints(?DateTime $time = null): int
    {
        if ($time === null || $time < $this->firstDeadline) {
            return $this->maxPointsBeforeFirstDeadline;
        } else {
            if ($this->allowSecondDeadline && $time < $this->secondDeadline) {
                if ($this->maxPointsDeadlineInterpolation) {
                    // get timestamps of first deadline, second deadline, and current time
                    $ts1 = $this->firstDeadline->getTimestamp();
                    $ts2 = $this->secondDeadline->getTimestamp();
                    $ts = $time->getTimestamp();

                    $deltaP = $this->maxPointsBeforeFirstDeadline - $this->maxPointsBeforeSecondDeadline;
                    $sign = ($deltaP > 0) - ($deltaP < 0); // neat way of getting signum of $deltaP

                    // linear interpolation: how many points are subtracted from first max at $ts time
                    $sub = $sign * (int)ceil((float)($ts - $ts1) * abs($deltaP) / (float)($ts2 - $ts1));
                    return $this->maxPointsBeforeFirstDeadline - $sub;
                } else {
                    return $this->maxPointsBeforeSecondDeadline;
                }
            } else {
                return 0;
            }
        }
    }

    /**
     * True if assignment has any points assigned, eq. some is non-zero.
     * @return bool
     */
    public function hasAssignedPoints(): bool
    {
        return $this->maxPointsBeforeFirstDeadline !== 0 || $this->maxPointsBeforeSecondDeadline !== 0;
    }

    /**
     * @ORM\Column(type="boolean")
     * Whether a student can see the relative consumed time and memory (for each test).
     */
    protected $canViewLimitRatios = false;

    /**
     * @ORM\Column(type="boolean")
     * Whether a student can see the absolute values of consumed time and memory (for each test).
     * This only applies if $canViewLimitRatios is true.
     */
    protected $canViewMeasuredValues = false;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $canViewJudgeStdout = false;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $canViewJudgeStderr = false;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $mergeJudgeLogs = false;

    /**
     * @ORM\Column(type="boolean")
     * True if this is an assignment dedicated for an exam.
     * Exam assignments are visualized differently and have auto-synced visibility and deadline with exam period.
     */
    protected $exam = false;

    /**
     * @ORM\ManyToOne(targetEntity="Exercise", inversedBy="assignments")
     */
    protected $exercise;

    public function getExercise(): ?Exercise
    {
        return $this->exercise->isDeleted() ? null : $this->exercise;
    }

    /**
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="assignments")
     */
    protected $group;

    public function getGroup(): ?Group
    {
        return $this->group->isDeleted() ? null : $this->group;
    }

    /**
     * @ORM\OneToMany(targetEntity="AssignmentSolution", mappedBy="assignment")
     */
    protected $assignmentSolutions;

    /**
     * @ORM\ManyToMany(targetEntity="RuntimeEnvironment")
     * @ORM\JoinTable(name="assignment_disabled_runtime_environments")
     */
    protected $disabledRuntimeEnvironments;

    public function getRuntimeEnvironments(): ReadableCollection
    {
        return $this->runtimeEnvironments->filter(
            function (RuntimeEnvironment $environment) {
                return !$this->disabledRuntimeEnvironments->contains($environment);
            }
        );
    }

    public function setDisabledRuntimeEnvironments(array $disabledRuntimes)
    {
        $this->disabledRuntimeEnvironments->clear();
        foreach ($disabledRuntimes as $environment) {
            $this->disabledRuntimeEnvironments->add($environment);
        }
    }

    public function getAllRuntimeEnvironmentsIds(): array
    {
        return $this->runtimeEnvironments->map(
            function (RuntimeEnvironment $environment) {
                return $environment->getId();
            }
        )->getValues();
    }

    public function getDisabledRuntimeEnvironmentsIds(): array
    {
        return $this->disabledRuntimeEnvironments->map(
            function (RuntimeEnvironment $environment) {
                return $environment->getId();
            }
        )->getValues();
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $syncedAt;

    public function getSyncedAt(): DateTime
    {
        return $this->syncedAt;
    }

    public function areRuntimeEnvironmentConfigsInSync(): bool
    {
        $exercise = $this->getExercise();
        return $exercise && $this->getRuntimeEnvironments()->forAll(
            function ($key, RuntimeEnvironment $env) use ($exercise) {
                $ours = $this->getExerciseEnvironmentConfigByEnvironment($env);
                $theirs = $exercise->getExerciseEnvironmentConfigByEnvironment($env);
                return $ours === $theirs;
            }
        );
    }

    public function areHardwareGroupsInSync(): bool
    {
        $exercise = $this->getExercise();
        return $exercise
            && $this->getHardwareGroups()->count() === $exercise->getHardwareGroups()->count()
            && $this->getHardwareGroups()->forAll(
                function ($key, HardwareGroup $group) use ($exercise) {
                    return $exercise->getHardwareGroups()->contains($group);
                }
            );
    }

    public function areLocalizedTextsInSync(): bool
    {
        $exercise = $this->getExercise();
        if (!$exercise) {
            return false;
        }

        $theirLocales = $exercise->getLocalizedTextsAssocArray();
        $ourLocales = $this->getLocalizedTextsAssocArray();
        $missingTheirs = false;

        // try to match our locales with exercise locales
        foreach ($ourLocales as $locale => $ours) {
            if (empty($theirLocales[$locale])) {
                $missingTheirs = true;
                continue;
            }

            $theirs = $theirLocales[$locale];
            if (!$ours->equals($theirs) && $theirs->getCreatedAt() > $ours->getCreatedAt()) {
                return false; // at least one locale is out of sync, no need to examine further
            }

            unset($theirLocales[$locale]); // already processed -> remove it
        }

        $missingOurs = (bool)$theirLocales; // some exercise locales were not processed

        // if some locales are missing on either side, the exercise must have been modified after the last sync
        return (!$missingTheirs && !$missingOurs) || $this->getSyncedAt() >= $exercise->getUpdatedAt();
    }

    public function areLimitsInSync(): bool
    {
        return $this->getExercise()
            && $this->areRuntimeEnvironmentConfigsInSync() && $this->areHardwareGroupsInSync()
            && $this->runtimeEnvironments->forAll(
                function ($key, RuntimeEnvironment $env) {
                    return $this->hardwareGroups->forAll(
                        function ($key, HardwareGroup $group) use ($env) {
                            $ours = $this->getLimitsByEnvironmentAndHwGroup($env, $group);
                            $theirs = $this->getExercise()->getLimitsByEnvironmentAndHwGroup($env, $group);
                            return $ours === $theirs;
                        }
                    );
                }
            );
    }

    public function areExerciseTestsInSync(): bool
    {
        $exercise = $this->getExercise();
        return $exercise
            && $this->getExerciseTests()->count() === $exercise->getExerciseTests()->count()
            && $this->getExerciseTests()->forAll(
                function ($key, ExerciseTest $test) use ($exercise) {
                    return $exercise->getExerciseTests()->contains($test);
                }
            );
    }

    public function areExerciseFilesInSync(): bool
    {
        $exercise = $this->getExercise();
        return $exercise
            && $this->getExerciseFiles()->count()
            === $exercise->getExerciseFiles()->count()
            && $this->getExerciseFiles()->forAll(
                function ($key, ExerciseFile $file) use ($exercise) {
                    return $exercise->getExerciseFiles()->contains($file);
                }
            );
    }

    public function areAttachmentFilesInSync(): bool
    {
        $exercise = $this->getExercise();
        return $exercise
            && $this->getAttachmentFiles()->count() === $exercise->getAttachmentFiles()->count()
            && $this->getAttachmentFiles()->forAll(
                function ($key, AttachmentFile $file) use ($exercise) {
                    return $exercise->getAttachmentFiles()->contains($file);
                }
            );
    }

    public function areRuntimeEnvironmentsInSync(): bool
    {
        $exercise = $this->getExercise();
        return $exercise
            && $this->runtimeEnvironments->count() === $this->exercise->getRuntimeEnvironments()->count()
            && $this->runtimeEnvironments->forAll(
                function ($key, RuntimeEnvironment $env) use ($exercise) {
                    return $exercise->getRuntimeEnvironments()->contains($env);
                }
            );
    }

    public function syncWithExercise()
    {
        $exercise = $this->getExercise();
        if ($exercise === null) {
            // cannot sync exercise was deleted
            return;
        }

        $this->mergeJudgeLogs = $exercise->getMergeJudgeLogs();

        $this->hardwareGroups->clear();
        foreach ($exercise->getHardwareGroups() as $group) {
            $this->hardwareGroups->add($group);
        }

        $this->localizedTexts->clear();
        foreach ($exercise->getLocalizedTexts() as $text) {
            $this->localizedTexts->add($text);
        }

        $this->exerciseConfig = $exercise->getExerciseConfig();
        $this->configurationType = $exercise->getConfigurationType();
        $this->scoreConfig = $exercise->getScoreConfig();

        $this->exerciseEnvironmentConfigs->clear();
        foreach ($exercise->getExerciseEnvironmentConfigs() as $config) {
            $this->exerciseEnvironmentConfigs->add($config);
        }

        $this->exerciseLimits->clear();
        foreach ($exercise->getExerciseLimits() as $limits) {
            $this->exerciseLimits->add($limits);
        }

        $this->exerciseTests->clear();
        foreach ($exercise->getExerciseTests() as $test) {
            $this->exerciseTests->add($test);
        }

        $this->exerciseFiles->clear();
        foreach ($exercise->getExerciseFiles() as $file) {
            $this->exerciseFiles->add($file);
        }

        $this->attachmentFiles->clear();
        foreach ($exercise->getAttachmentFiles() as $file) {
            $this->attachmentFiles->add($file);
        }

        $this->runtimeEnvironments->clear();
        foreach ($exercise->getRuntimeEnvironments() as $env) {
            $this->runtimeEnvironments->add($env);
        }

        $this->syncedAt = new DateTime();
    }

    /**
     * @var PlagiarismDetectionBatch|null
     * @ORM\ManyToOne(targetEntity="PlagiarismDetectionBatch")
     * Refers to last plagiarism detection batch which checked solutions of this assignment.
     */
    protected $plagiarismBatch = null;

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getPointsPercentualThreshold(): float
    {
        return $this->pointsPercentualThreshold;
    }

    public function setPointsPercentualThreshold($pointsPercentualThreshold): void
    {
        $this->pointsPercentualThreshold = $pointsPercentualThreshold;
    }

    public function getSubmissionsCountLimit(): int
    {
        return $this->submissionsCountLimit;
    }

    public function setSubmissionsCountLimit(int $submissionsCountLimit): void
    {
        $this->submissionsCountLimit = $submissionsCountLimit;
    }

    public function getAssignmentSolutions(): Collection
    {
        return $this->assignmentSolutions;
    }

    public function getCanViewLimitRatios(): bool
    {
        return $this->canViewLimitRatios;
    }

    public function setCanViewLimitRatios(bool $canViewLimitRatios): void
    {
        $this->canViewLimitRatios = $canViewLimitRatios;
    }

    public function getCanViewMeasuredValues(): bool
    {
        return $this->canViewMeasuredValues;
    }

    public function setCanViewMeasuredValues(bool $canViewMeasuredValues): void
    {
        $this->canViewMeasuredValues = $canViewMeasuredValues;
    }

    public function getFirstDeadline(): DateTime
    {
        return $this->firstDeadline;
    }

    public function setFirstDeadline(DateTime $firstDeadline): void
    {
        $this->firstDeadline = $firstDeadline;
    }

    public function getAllowSecondDeadline(): bool
    {
        return $this->allowSecondDeadline;
    }

    public function setAllowSecondDeadline(bool $allowSecondDeadline): void
    {
        $this->allowSecondDeadline = $allowSecondDeadline;
    }

    public function getSecondDeadline(): ?DateTime
    {
        return $this->secondDeadline;
    }

    public function setSecondDeadline(?DateTime $secondDeadline): void
    {
        $this->secondDeadline = $secondDeadline;
    }

    public function getMaxPointsBeforeFirstDeadline(): int
    {
        return $this->maxPointsBeforeFirstDeadline;
    }

    public function setMaxPointsBeforeFirstDeadline(int $maxPointsBeforeFirstDeadline): void
    {
        $this->maxPointsBeforeFirstDeadline = $maxPointsBeforeFirstDeadline;
    }

    public function getMaxPointsBeforeSecondDeadline(): int
    {
        return $this->maxPointsBeforeSecondDeadline;
    }

    public function setMaxPointsBeforeSecondDeadline(int $maxPointsBeforeSecondDeadline): void
    {
        $this->maxPointsBeforeSecondDeadline = $maxPointsBeforeSecondDeadline;
    }

    public function getVisibleFrom(): ?DateTime
    {
        return $this->visibleFrom;
    }

    public function setVisibleFrom(?DateTime $visibleFrom): void
    {
        $this->visibleFrom = $visibleFrom;
    }

    public function getCanViewJudgeStdout(): bool
    {
        return $this->canViewJudgeStdout;
    }

    public function setCanViewJudgeStdout(bool $canViewJudgeStdout): void
    {
        $this->canViewJudgeStdout = $canViewJudgeStdout;
    }

    public function getCanViewJudgeStderr(): bool
    {
        return $this->canViewJudgeStderr;
    }

    public function setCanViewJudgeStderr(bool $canViewJudgeStderr): void
    {
        $this->canViewJudgeStderr = $canViewJudgeStderr;
    }

    public function getMergeJudgeLogs(): bool
    {
        return $this->mergeJudgeLogs;
    }

    public function isExam(): bool
    {
        return $this->exam;
    }

    public function setExam(bool $value = true): void
    {
        $this->exam = $value;
    }

    public function getMaxPointsDeadlineInterpolation(): bool
    {
        return $this->maxPointsDeadlineInterpolation;
    }

    public function setMaxPointsDeadlineInterpolation(bool $interpolation = true): void
    {
        $this->maxPointsDeadlineInterpolation = $interpolation;
    }

    public function getPlagiarismBatch(): ?PlagiarismDetectionBatch
    {
        return $this->plagiarismBatch;
    }

    public function setPlagiarismBatch(?PlagiarismDetectionBatch $batch = null)
    {
        $this->plagiarismBatch = $batch;
    }
}
