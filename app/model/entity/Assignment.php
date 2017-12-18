<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidStateException;
use App\Helpers\Evaluation\IExercise;
use App\Helpers\Localizations;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use DateTime;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="first_deadline_idx", columns={"first_deadline"}), @ORM\Index(name="second_deadline_idx", columns={"second_deadline"})})
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method DateTime getDeletedAt()
 * @method Collection getRuntimeEnvironments()
 * @method int getPointsPercentualThreshold()
 * @method int getSubmissionsCountLimit()
 * @method Collection getAssignmentSolutions()
 * @method bool getCanViewLimitRatios()
 * @method Group getGroup()
 * @method DateTime getCreatedAt()
 * @method Exercise getExercise()
 * @method DateTime getFirstDeadline()
 * @method DateTime getSecondDeadline()
 * @method int getMaxPointsBeforeFirstDeadline()
 * @method int getMaxPointsBeforeSecondDeadline()
 * @method int getVersion()
 * @method setFirstDeadline(DateTime $deadline)
 * @method setSecondDeadline(DateTime $deadline)
 * @method setUpdatedAt(DateTime $date)
 * @method setIsPublic(bool $public)
 * @method setMaxPointsBeforeFirstDeadline(int $points)
 * @method setMaxPointsBeforeSecondDeadline(int $points)
 * @method setSubmissionsCountLimit(int $limit)
 * @method setAllowSecondDeadline(bool $allow)
 * @method setCanViewLimitRatios(bool $canView)
 * @method setIsBonus(bool $bonus)
 * @method setPointsPercentualThreshold(float $threshold)
 */
class Assignment implements JsonSerializable, IExercise
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
    bool $canViewLimitRatios = FALSE,
    bool $isBonus = FALSE,
    $pointsPercentualThreshold = 0
  ) {
    if ($secondDeadline == null) {
      $secondDeadline = $firstDeadline;
    }

    $this->exercise = $exercise;
    $this->group = $group;
    $this->firstDeadline = $firstDeadline;
    $this->maxPointsBeforeFirstDeadline = $maxPointsBeforeFirstDeadline;
    $this->allowSecondDeadline = $allowSecondDeadline;
    $this->secondDeadline = $secondDeadline;
    $this->maxPointsBeforeSecondDeadline = $maxPointsBeforeSecondDeadline;
    $this->assignmentSolutions = new ArrayCollection;
    $this->isPublic = $isPublic;
    $this->runtimeEnvironments = $exercise->getRuntimeEnvironments();
    $this->hardwareGroups = new ArrayCollection($exercise->getHardwareGroups()->toArray());
    $this->exerciseTests = new ArrayCollection($exercise->getExerciseTests()->toArray());
    $this->exerciseLimits = new ArrayCollection($exercise->getExerciseLimits()->toArray());
    $this->exerciseEnvironmentConfigs = new ArrayCollection($exercise->getExerciseEnvironmentConfigs()->toArray());
    $this->exerciseConfig = $exercise->getExerciseConfig();
    $this->submissionsCountLimit = $submissionsCountLimit;
    $this->scoreConfig = $exercise->getScoreConfig();
    $this->scoreCalculator = $exercise->getScoreCalculator();
    $this->localizedTexts = new ArrayCollection($exercise->getLocalizedTexts()->toArray());
    $this->canViewLimitRatios = $canViewLimitRatios;
    $this->version = 1;
    $this->isBonus = $isBonus;
    $this->pointsPercentualThreshold = $pointsPercentualThreshold;
    $this->createdAt = new \DateTime;
    $this->updatedAt = new \DateTime;
    $this->configurationType = $exercise->getConfigurationType();
    $this->supplementaryEvaluationFiles = $exercise->getSupplementaryEvaluationFiles();
    $this->attachmentFiles = $exercise->getAttachmentFiles();
  }

  public static function assignToGroup(Exercise $exercise, Group $group, $isPublic = FALSE) {
    if ($exercise->getLocalizedTexts()->count() == 0) {
      throw new InvalidStateException("There are no localized descriptions of exercise");
    }

    if ($exercise->getRuntimeEnvironments()->count() == 0) {
      throw new InvalidStateException("There are no runtime environments in exercise");
    }

    $assignment = new self(
      new DateTime,
      0,
      $exercise,
      $group,
      $isPublic,
      50,
      FALSE
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
   * @ORM\Column(type="integer")
   */
  protected $version;

  /**
   * Increment version number.
   */
  public function incrementVersion() {
    $this->version++;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPublic;

  public function isPublic() {
    return $this->isPublic;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isBonus;

  /**
   * @ORM\Column(type="float")
   */
  protected $pointsPercentualThreshold;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $updatedAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $deletedAt;

  /**
   * @ORM\Column(type="smallint")
   */
  protected $submissionsCountLimit;

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
      $now = new \DateTime;
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

  public function getMaxPoints(DateTime $time = NULL) {
    if ($time === NULL || $time < $this->firstDeadline) {
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
   * Assignment can be marked as bonus, then we do not want to add its points
   * to overall maximum points of group. This function will return 0 if
   * assignment is marked as bonus one, otherwise it will return result of
   * $this->getMaxPoints() function.
   * @return int
   */
  public function getGroupPoints(): int {
    if ($this->isBonus) {
      return 0;
    } else {
      return $this->getMaxPoints();
    }
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

  /**
   * @ORM\OneToMany(targetEntity="AssignmentSolution", mappedBy="assignment")
   */
  protected $assignmentSolutions;

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

  public function jsonSerialize() {
    $envConfigsInSync = $this->getRuntimeEnvironments()->forAll(
      function ($key, RuntimeEnvironment $env) {
        $ours = $this->getExerciseEnvironmentConfigByEnvironment($env);
        $theirs = $this->getExercise()->getExerciseEnvironmentConfigByEnvironment($env);
        return $ours === $theirs;
      }
    );

    $hwGroupsInSync = $this->getHardwareGroups()->count() === $this->getExercise()->getHardwareGroups()->count()
      && $this->getHardwareGroups()->forAll(function ($key, HardwareGroup $group) {
        return $this->getExercise()->getHardwareGroups()->contains($group);
      });

    /** @var LocalizedExercise $primaryLocalization */
    $primaryLocalization = Localizations::getPrimaryLocalization($this->localizedTexts);

    return [
      "id" => $this->id,
      "name" => $primaryLocalization ? $primaryLocalization->getName() : "", # BC
      "version" => $this->version,
      "isPublic" => $this->isPublic,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "localizedTexts" => $this->localizedTexts->getValues(),
      "groupId" => $this->group->getId(),
      "firstDeadline" => $this->firstDeadline->getTimestamp(),
      "secondDeadline" => $this->secondDeadline->getTimestamp(),
      "allowSecondDeadline" => $this->allowSecondDeadline,
      "maxPointsBeforeFirstDeadline" => $this->maxPointsBeforeFirstDeadline,
      "maxPointsBeforeSecondDeadline" => $this->maxPointsBeforeSecondDeadline,
      "submissionsCountLimit" => $this->submissionsCountLimit,
      "canReceiveSubmissions" => FALSE, // the app must perform a special request to get the valid information TODO why is it still here then?
      "runtimeEnvironmentsIds" => $this->getRuntimeEnvironmentsIds(),
      "canViewLimitRatios" => $this->canViewLimitRatios,
      "isBonus" => $this->isBonus,
      "pointsPercentualThreshold" => $this->pointsPercentualThreshold,
      "exerciseSynchronizationInfo" => [
        "exerciseConfig" => [
          "upToDate" => $this->getExerciseConfig() === $this->getExercise()->getExerciseConfig(),
        ],
        "configurationType" => [
          "upToDate" => $this->configurationType === $this->getExercise()->getConfigurationType()
        ],
        "scoreConfig" => [
          "upToDate" => $this->getScoreConfig() === $this->getExercise()->getScoreConfig(),
        ],
        "scoreCalculator" => [
          "upToDate" => $this->getScoreCalculator() === $this->getExercise()->getScoreCalculator(),
        ],
        "exerciseEnvironmentConfigs" => [
          "upToDate" => $envConfigsInSync
        ],
        "hardwareGroups" => [
          "upToDate" => $hwGroupsInSync
        ],
        "localizedTexts" => [
          "upToDate" => $this->getLocalizedTexts()->count() >= $this->getExercise()->getLocalizedTexts()->count()
              && $this->getLocalizedTexts()->forAll(function ($key, LocalizedExercise $ours) {
            $theirs = $this->getExercise()->getLocalizedTextByLocale($ours->getLocale());
            return $theirs === NULL || $ours->equals($theirs) || $theirs->getCreatedAt() < $ours->getCreatedAt();
          })
        ],
        "limits" => [
          "upToDate" => $envConfigsInSync && $hwGroupsInSync && $this->runtimeEnvironments->forAll(function ($key, RuntimeEnvironment $env) {
            return $this->hardwareGroups->forAll(function ($key, HardwareGroup $group) use ($env) {
              $ours = $this->getLimitsByEnvironmentAndHwGroup($env, $group);
              $theirs = $this->getExercise()->getLimitsByEnvironmentAndHwGroup($env, $group);
              return $ours === $theirs;
            });
          })
        ],
        "exerciseTests" => [
          "upToDate" => $this->getExerciseTests()->count() === $this->getExercise()->getExerciseTests()->count()
            && $this->getExerciseTests()->forAll(function ($key, ExerciseTest $test) {
              return $this->getExercise()->getExerciseTests()->contains($test);
            })
        ],
        "supplementaryFiles" => [
          "upToDate" => $this->getSupplementaryEvaluationFiles()->count() === $this->getExercise()->getSupplementaryEvaluationFiles()->count()
            && $this->getSupplementaryEvaluationFiles()->forAll(function ($key, SupplementaryExerciseFile $file) {
              return $this->getExercise()->getSupplementaryEvaluationFiles()->contains($file);
            })
        ],
        "attachmentFiles" => [
          "upToDate" => $this->getAttachmentFiles()->count() === $this->getExercise()->getAttachmentFiles()->count()
            && $this->getAttachmentFiles()->forAll(function ($key, AttachmentFile $file) {
              return $this->getExercise()->getAttachmentFiles()->contains($file);
            })
        ],
        "runtimeEnvironments" => [
          "upToDate" => $this->getRuntimeEnvironments()->count() === $this->getExercise()->getRuntimeEnvironments()->count()
            && $this->getRuntimeEnvironments()->forAll(function ($key, RuntimeEnvironment $env) {
              return $this->getExercise()->getRuntimeEnvironments()->contains($env);
            })
        ]
      ]
    ];
  }
}
