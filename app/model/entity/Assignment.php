<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidStateException;
use App\Helpers\Evaluation\IExercise;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use DateTime;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="first_deadline_idx", columns={"first_deadline"}), @ORM\Index(name="second_deadline_idx", columns={"second_deadline"})})
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method int getPointsPercentualThreshold()
 * @method int getSubmissionsCountLimit()
 * @method Collection getAssignmentSolutions()
 * @method bool getCanViewLimitRatios()
 * @method Exercise getExercise()
 * @method DateTime getFirstDeadline()
 * @method bool getAllowSecondDeadline()
 * @method DateTime getSecondDeadline()
 * @method int getMaxPointsBeforeFirstDeadline()
 * @method int getMaxPointsBeforeSecondDeadline()
 * @method DateTime getVisibleFrom()
 * @method setFirstDeadline(DateTime $deadline)
 * @method setSecondDeadline(DateTime $deadline)
 * @method setMaxPointsBeforeFirstDeadline(int $points)
 * @method setMaxPointsBeforeSecondDeadline(int $points)
 * @method setSubmissionsCountLimit(int $limit)
 * @method setAllowSecondDeadline(bool $allow)
 * @method setCanViewLimitRatios(bool $canView)
 * @method setPointsPercentualThreshold(float $threshold)
 * @method setVisibleFrom(DateTime $visibleFrom)
 */
class Assignment extends AssignmentBase implements IExercise
{
  use MagicAccessors;
  use ExerciseData;

  private function __construct(
    DateTime $firstDeadline,
    int $maxPointsBeforeFirstDeadline,
    Exercise $exercise,
    Group $group,
    bool $isPublic,
    int $submissionsCountLimit,
    bool $allowSecondDeadline,
    DateTime $secondDeadline = null,
    int $maxPointsBeforeSecondDeadline = 0,
    bool $canViewLimitRatios = false,
    bool $isBonus = false,
    $pointsPercentualThreshold = 0,
    ?DateTime $visibleFrom = null
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
    $this->scoreCalculator = $exercise->getScoreCalculator();
    $this->localizedTexts = new ArrayCollection($exercise->getLocalizedTexts()->toArray());
    $this->localizedAssignments = new ArrayCollection();
    $this->canViewLimitRatios = $canViewLimitRatios;
    $this->version = 1;
    $this->isBonus = $isBonus;
    $this->pointsPercentualThreshold = $pointsPercentualThreshold;
    $this->createdAt = new \DateTime();
    $this->updatedAt = new \DateTime();
    $this->configurationType = $exercise->getConfigurationType();
    $this->supplementaryEvaluationFiles = $exercise->getSupplementaryEvaluationFiles();
    $this->attachmentFiles = $exercise->getAttachmentFiles();
  }

  public static function assignToGroup(Exercise $exercise, Group $group, $isPublic = false, DateTime $firstDeadline = null) {
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
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
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

  public function getLocalizedAssignments(): Collection {
    return $this->localizedAssignments;
  }

  public function addLocalizedAssignment(LocalizedAssignment $localizedAssignment) {
    $this->localizedAssignments->add($localizedAssignment);
  }

  /**
   * Get localized assignment text based on given locale.
   * @param string $locale
   * @return LocalizedAssignment|null
   */
  public function getLocalizedAssignmentByLocale(string $locale): ?LocalizedAssignment {
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

  public function isAfterDeadline(\DateTime $now = null) {
    if ($now === null) {
      $now = new \DateTime();
    }

    if ($this->allowSecondDeadline) {
      return $this->secondDeadline < $now;
    } else {
      return $this->firstDeadline < $now;
    }
  }

  /**
   * @ORM\Column(type="smallint")
   */
  protected $maxPointsBeforeFirstDeadline;

  /**
   * @ORM\Column(type="smallint")
   */
  protected $maxPointsBeforeSecondDeadline;

  public function getMaxPoints(DateTime $time = null): int {
    if ($time === null || $time < $this->firstDeadline) {
      return $this->maxPointsBeforeFirstDeadline;
    } else if ($this->allowSecondDeadline && $time < $this->secondDeadline) {
      return $this->maxPointsBeforeSecondDeadline;
    } else {
      return 0;
    }
  }

  /**
   * True if assignment has any points assigned, eq. some is non-zero.
   * @return bool
   */
  public function hasAssignedPoints(): bool {
    return $this->maxPointsBeforeFirstDeadline !== 0 || $this->maxPointsBeforeSecondDeadline !== 0;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $canViewLimitRatios;

  /**
   * @ORM\ManyToOne(targetEntity="Exercise")
   */
  protected $exercise;

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="assignments")
   */
  protected $group;

  public function getGroup(): Group {
    return $this->group;
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

  public function getRuntimeEnvironments(): Collection {
    return $this->runtimeEnvironments->filter(function (RuntimeEnvironment $environment) {
      return !$this->disabledRuntimeEnvironments->contains($environment);
    });
  }

  public function setDisabledRuntimeEnvironments(array $disabledRuntimes) {
    $this->disabledRuntimeEnvironments->clear();
    foreach ($disabledRuntimes as $environment) {
      $this->disabledRuntimeEnvironments->add($environment);
    }
  }

  public function getAllRuntimeEnvironmentsIds(): array {
    return $this->runtimeEnvironments->map(function (RuntimeEnvironment $environment) {
      return $environment->getId();
    })->getValues();
  }

  public function getDisabledRuntimeEnvironmentsIds(): array {
    return $this->disabledRuntimeEnvironments->map(function (RuntimeEnvironment $environment) {
      return $environment->getId();
    })->getValues();
  }

  public function areRuntimeEnvironmentConfigsInSync(): bool {
    return $this->getRuntimeEnvironments()->forAll(
      function ($key, RuntimeEnvironment $env) {
        $ours = $this->getExerciseEnvironmentConfigByEnvironment($env);
        $theirs = $this->getExercise()->getExerciseEnvironmentConfigByEnvironment($env);
        return $ours === $theirs;
      }
    );
  }

  public function areHardwareGroupsInSync(): bool {
    return $this->getHardwareGroups()->count() === $this->getExercise()->getHardwareGroups()->count()
      && $this->getHardwareGroups()->forAll(function ($key, HardwareGroup $group) {
        return $this->getExercise()->getHardwareGroups()->contains($group);
      });
  }

  public function areLocalizedTextsInSync(): bool {
    return $this->getLocalizedTexts()->count() >= $this->getExercise()->getLocalizedTexts()->count()
      && $this->getLocalizedTexts()->forAll(function ($key, LocalizedExercise $ours) {
        $theirs = $this->getExercise()->getLocalizedTextByLocale($ours->getLocale());
        return $theirs === null || $ours->equals($theirs) || $theirs->getCreatedAt() < $ours->getCreatedAt();
      });
  }

  public function areLimitsInSync(): bool {
    return $this->areRuntimeEnvironmentConfigsInSync() && $this->areHardwareGroupsInSync()
      && $this->runtimeEnvironments->forAll(function ($key, RuntimeEnvironment $env) {
        return $this->hardwareGroups->forAll(function ($key, HardwareGroup $group) use ($env) {
          $ours = $this->getLimitsByEnvironmentAndHwGroup($env, $group);
          $theirs = $this->getExercise()->getLimitsByEnvironmentAndHwGroup($env, $group);
          return $ours === $theirs;
        });
      });
  }

  public function areExerciseTestsInSync(): bool {
    return $this->getExerciseTests()->count() === $this->getExercise()->getExerciseTests()->count()
      && $this->getExerciseTests()->forAll(function ($key, ExerciseTest $test) {
        return $this->getExercise()->getExerciseTests()->contains($test);
      });
  }

  public function areSupplementaryFilesInSync(): bool {
    return $this->getSupplementaryEvaluationFiles()->count() === $this->getExercise()->getSupplementaryEvaluationFiles()->count()
      && $this->getSupplementaryEvaluationFiles()->forAll(function ($key, SupplementaryExerciseFile $file) {
        return $this->getExercise()->getSupplementaryEvaluationFiles()->contains($file);
      });
  }

  public function areAttachmentFilesInSync(): bool {
    return $this->getAttachmentFiles()->count() === $this->getExercise()->getAttachmentFiles()->count()
      && $this->getAttachmentFiles()->forAll(function ($key, AttachmentFile $file) {
        return $this->getExercise()->getAttachmentFiles()->contains($file);
      });
  }

  public function areRuntimeEnvironmentsInSync(): bool {
    return $this->runtimeEnvironments->count() === $this->getExercise()->getRuntimeEnvironments()->count()
      && $this->runtimeEnvironments->forAll(function ($key, RuntimeEnvironment $env) {
        return $this->getExercise()->getRuntimeEnvironments()->contains($env);
      });
  }

  public function syncWithExercise() {
    $exercise = $this->getExercise();

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
    $this->scoreCalculator = $exercise->getScoreCalculator();

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

    $this->supplementaryEvaluationFiles->clear();
    foreach ($exercise->getSupplementaryEvaluationFiles() as $file) {
      $this->supplementaryEvaluationFiles->add($file);
    }

    $this->attachmentFiles->clear();
    foreach ($exercise->getAttachmentFiles() as $file) {
      $this->attachmentFiles->add($file);
    }

    $this->runtimeEnvironments->clear();
    foreach ($exercise->getRuntimeEnvironments() as $env) {
      $this->runtimeEnvironments->add($env);
    }
  }

}
