<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidStateException;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use DateTime;
use Kdyby\Doctrine\Entities\MagicAccessors;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="first_deadline_idx", columns={"first_deadline"}), @ORM\Index(name="second_deadline_idx", columns={"second_deadline"})})
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method string getName()
 * @method DateTime getDeletedAt()
 * @method string getScoreCalculator()
 * @method Collection getRuntimeEnvironments()
 * @method Collection getHardwareGroups()
 * @method int getPointsPercentualThreshold()
 * @method int getSubmissionsCountLimit()
 * @method Collection getSubmissions()
 * @method bool getCanViewLimitRatios()
 * @method Group getGroup()
 * @method Collection getLocalizedTexts()
 * @method removeLocalizedText(LocalizedText $assignment)
 * @method DateTime getCreatedAt()
 * @method Exercise getExercise()
 * @method string getScoreConfig()
 * @method ExerciseConfig getExerciseConfig()
 * @method void setScoreConfig(string $scoreConfig)
 * @method DateTime getFirstDeadline()
 * @method DateTime getSecondDeadline()
 * @method int getMaxPointsBeforeFirstDeadline()
 * @method setName(string $name)
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
class Assignment implements JsonSerializable
{
  use MagicAccessors;

  private function __construct(
    string $name,
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

    $this->name = $name;
    $this->exercise = $exercise;
    $this->group = $group;
    $this->firstDeadline = $firstDeadline;
    $this->maxPointsBeforeFirstDeadline = $maxPointsBeforeFirstDeadline;
    $this->allowSecondDeadline = $allowSecondDeadline;
    $this->secondDeadline = $secondDeadline;
    $this->maxPointsBeforeSecondDeadline = $maxPointsBeforeSecondDeadline;
    $this->submissions = new ArrayCollection;
    $this->isPublic = $isPublic;
    $this->runtimeEnvironments = $exercise->getRuntimeEnvironments();
    $this->hardwareGroups = new ArrayCollection($exercise->getHardwareGroups()->toArray());
    $this->exerciseLimits = new ArrayCollection($exercise->getExerciseLimits()->toArray());
    $this->exerciseEnvironmentConfigs = new ArrayCollection($exercise->getExerciseEnvironmentConfigs()->toArray());
    $this->exerciseConfig = $exercise->getExerciseConfig();
    $this->submissionsCountLimit = $submissionsCountLimit;
    $this->scoreConfig = "";
    $this->localizedTexts = new ArrayCollection($exercise->getLocalizedTexts()->toArray());
    $this->canViewLimitRatios = $canViewLimitRatios;
    $this->version = 1;
    $this->isBonus = $isBonus;
    $this->pointsPercentualThreshold = $pointsPercentualThreshold;
    $this->createdAt = new \DateTime;
    $this->updatedAt = new \DateTime;
  }

  public static function assignToGroup(Exercise $exercise, Group $group, $isPublic = FALSE) {
    if ($exercise->getLocalizedTexts()->count() == 0) {
      throw new InvalidStateException("There are no localized descriptions of exercise");
    }

    if ($exercise->getRuntimeEnvironments()->count() == 0) {
      throw new InvalidStateException("There are no runtime environments in exercise");
    }

    $assignment = new self(
      $exercise->getName(),
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
   * @ORM\Column(type="string")
   */
  protected $name;

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
   * @ORM\ManyToMany(targetEntity="RuntimeEnvironment")
   * @var Collection
   */
  protected $runtimeEnvironments;

  /**
   * @ORM\ManyToMany(targetEntity="HardwareGroup")
   * @var Collection
   */
  protected $hardwareGroups;

  /**
   * Get IDs of all defined hardware groups.
   * @return string[]
   */
  public function getHardwareGroupsIds() {
    return $this->hardwareGroups->map(function($group) { return $group->getId(); })->getValues();
  }

  /**
   * @ORM\ManyToMany(targetEntity="ExerciseLimits", inversedBy="exercises", cascade={"persist"})
   */
  protected $exerciseLimits;

  /**
   * Get exercise limits based on environment and hardware group.
   * @param RuntimeEnvironment $environment
   * @param HardwareGroup $hwGroup
   * @return ExerciseLimits|NULL
   */
  public function getLimitsByEnvironmentAndHwGroup(RuntimeEnvironment $environment, HardwareGroup $hwGroup): ?ExerciseLimits {
    $first = $this->exerciseLimits->filter(
      function (ExerciseLimits $exerciseLimits) use ($environment, $hwGroup) {
        return $exerciseLimits->getRuntimeEnvironment()->getId() === $environment->getId()
          && $exerciseLimits->getHardwareGroup()->getId() === $hwGroup->getId();
      })->first();
    return $first === FALSE ? NULL : $first;
  }

  /**
   * @ORM\ManyToMany(targetEntity="ExerciseEnvironmentConfig", inversedBy="exercises", cascade={"persist"})
   * @var Collection|Selectable
   */
  protected $exerciseEnvironmentConfigs;

  /**
   * Get runtime configuration based on environment identification.
   * @param RuntimeEnvironment $environment
   * @return ExerciseEnvironmentConfig|NULL
   */
  public function getExerciseEnvironmentConfigByEnvironment(RuntimeEnvironment $environment) {
    $first = $this->exerciseEnvironmentConfigs->filter(
      function (ExerciseEnvironmentConfig $runtimeConfig) use ($environment) {
        return $runtimeConfig->getRuntimeEnvironment()->getId() === $environment->getId();
      })->first();
    return $first === false ? null : $first;
  }

  /**
   * @ORM\ManyToOne(targetEntity="ExerciseConfig", inversedBy="exercises")
   */
  protected $exerciseConfig;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $scoreCalculator;

  /**
   * @ORM\Column(type="text", nullable=true)
   */
  protected $scoreConfig;

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

  public function isAfterDeadline() {
    if ($this->allowSecondDeadline) {
      return $this->secondDeadline < new \DateTime;
    } else {
      return $this->firstDeadline < new \DateTime;
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
   * @ORM\ManyToMany(targetEntity="LocalizedText", inversedBy="assignments")
   * @var Collection|Selectable
   */
  protected $localizedTexts;

  public function addLocalizedText(LocalizedText $assignment) {
    $this->localizedTexts->add($assignment);
  }

  public function getLocalizedTextByLocale($locale) {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
    $first = $this->localizedTexts->matching($criteria)->first();
    return $first === FALSE ? NULL : $first;
  }

  /**
   * @ORM\ManyToOne(targetEntity="Exercise")
   */
  protected $exercise;

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="assignments")
   */
  protected $group;

  public function canReceiveSubmissions(User $user = NULL) {
    return $this->isPublic === TRUE &&
      $this->group->hasValidLicence() &&
      !$this->isAfterDeadline() &&
      ($user !== NULL && !$this->hasReachedSubmissionsCountLimit($user));
  }

  /**
   * @ORM\OneToMany(targetEntity="Submission", mappedBy="assignment")
   * @ORM\OrderBy({ "submittedAt" = "DESC" })
   */
  protected $submissions;

  /**
   * @param User $user
   * @return Submission[]
   */
  public function getValidSubmissions(User $user) {
    $fromThatUser = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user))
      ->andWhere(Criteria::expr()->neq("resultsUrl", NULL));
    $validSubmissions = function (Submission $submission) {
      if (!$submission->hasEvaluation()) {
        // the submission is not evaluated yet - suppose it will be evaluated in the future (or marked as invalid)
        // -> otherwise the user would be able to submit many solutions before they are evaluated
        return TRUE;
      }

      // keep only solutions, which are marked as valid (both manual and automatic way)
      $evaluation = $submission->getEvaluation();
      return ($evaluation->isValid() === TRUE && $evaluation->getEvaluationFailed() === FALSE);
    };

    return $this->submissions
      ->matching($fromThatUser)
      ->filter($validSubmissions);
  }

  public function hasReachedSubmissionsCountLimit(User $user) {
    return count($this->getValidSubmissions($user)) >= $this->submissionsCountLimit;
  }

  /**
   * @param User $user
   * @return Submission
   */
  public function getLastSolution(User $user) {
    $usersSolutions = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user));
    return $this->submissions->matching($usersSolutions)->first();
  }

  /**
   * @param User $user
   * @return Submission|NULL
   */
  public function getBestSolution(User $user) {
    $usersSolutions = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user))
      ->andWhere(Criteria::expr()->neq("evaluation", NULL));

    return array_reduce(
      $this->submissions->matching($usersSolutions)->getValues(),
      function (?Submission $best, Submission $submission) {
        if ($best === NULL) {
          return $submission;
        }

        if ($best->isAccepted()) {
          return $best;
        }

        if ($submission->isAccepted()) {
          return $submission;
        }

        return $submission->hasEvaluation() === FALSE || $best->getTotalPoints() > $submission->getTotalPoints()
          ? $best
          : $submission;
      },
      NULL
    );
  }

  public function getRuntimeEnvironmentsIds() {
    return $this->runtimeEnvironments->map(function($config) { return $config->getId(); })->getValues();
  }

  protected function getSerializedLocalizedTexts() {
    return array_map(function (LocalizedText $text) {
      $data = $text->jsonSerialize();
      if ($data["shortText"] === NULL) {
        $data["shortText"] = $this->name;
      }
      return $data;
    }, $this->localizedTexts->getValues());
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

    $this->exerciseEnvironmentConfigs->clear();
    foreach ($exercise->getExerciseEnvironmentConfigs() as $config) {
      $this->exerciseEnvironmentConfigs->add($config);
    }

    $this->exerciseLimits->clear();
    foreach ($exercise->getExerciseLimits() as $limits) {
      $this->exerciseLimits->add($limits);
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

    return [
      "id" => $this->id,
      "name" => $this->name,
      "version" => $this->version,
      "isPublic" => $this->isPublic,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "localizedTexts" => $this->getSerializedLocalizedTexts(),
      "groupId" => $this->group->getId(),
      "firstDeadline" => $this->firstDeadline->getTimestamp(),
      "secondDeadline" => $this->secondDeadline->getTimestamp(),
      "allowSecondDeadline" => $this->allowSecondDeadline,
      "maxPointsBeforeFirstDeadline" => $this->maxPointsBeforeFirstDeadline,
      "maxPointsBeforeSecondDeadline" => $this->maxPointsBeforeSecondDeadline,
      "scoreConfig" => $this->scoreConfig,
      "submissionsCountLimit" => $this->submissionsCountLimit,
      "canReceiveSubmissions" => FALSE, // the app must perform a special request to get the valid information
      "runtimeEnvironmentsIds" => $this->getRuntimeEnvironmentsIds(),
      "canViewLimitRatios" => $this->canViewLimitRatios,
      "isBonus" => $this->isBonus,
      "pointsPercentualThreshold" => $this->pointsPercentualThreshold,
      "exerciseSynchronizationInfo" => [
        "exerciseConfig" => [
          "upToDate" => $this->getExerciseConfig() === $this->getExercise()->getExerciseConfig(),
        ],
        "exerciseEnvironmentConfigs" => [
          "upToDate" => $envConfigsInSync
        ],
        "hardwareGroups" => [
          "upToDate" => $hwGroupsInSync
        ],
        "localizedTexts" => [
          "upToDate" => $this->getLocalizedTexts()->count() >= $this->getExercise()->getLocalizedTexts()->count()
              && $this->getLocalizedTexts()->forAll(function ($key, LocalizedText $ours) {
            $theirs = $this->getExercise()->getLocalizedTextByLocale($ours->getLocale());
            return $theirs === NULL || $ours->equals($theirs);
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
        ]
      ]
    ];
  }
}
