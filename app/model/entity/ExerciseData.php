<?php
namespace App\Model\Entity;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;

trait ExerciseData {
  /**
   * @ORM\Column(type="string")
   */
  protected $configurationType;

  /**
   * @ORM\ManyToMany(targetEntity="LocalizedExercise", indexBy="locale")
   * @var Collection|Selectable
   */
  protected $localizedTexts;

  public function getLocalizedTexts(): Collection {
    return $this->localizedTexts;
  }

  public function addLocalizedText(LocalizedExercise $localizedText) {
    $this->localizedTexts->add($localizedText);
  }

  /**
   * Get localized text based on given locale.
   * @param string $locale
   * @return LocalizedExercise|NULL
   */
  public function getLocalizedTextByLocale(string $locale) {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
    $first = $this->localizedTexts->matching($criteria)->first();
    return $first === false ? null : $first;
  }

  /**
   * @ORM\ManyToMany(targetEntity="RuntimeEnvironment")
   * @var Collection
   */
  protected $runtimeEnvironments;

  /**
   * Get IDs of all available runtime environments
   * @return array
   */
  public function getRuntimeEnvironmentsIds() {
    return $this->runtimeEnvironments->map(function($config) { return $config->getId(); })->getValues();
  }

  /**
   * @ORM\ManyToMany(targetEntity="HardwareGroup")
   * @var Collection
   */
  protected $hardwareGroups;

  public function getHardwareGroups(): Collection {
    return $this->hardwareGroups;
  }

  /**
   * Get IDs of all defined hardware groups.
   * @return string[]
   */
  public function getHardwareGroupsIds() {
    return $this->hardwareGroups->map(function($group) { return $group->getId(); })->getValues();
  }

  /**
   * @ORM\ManyToMany(targetEntity="ExerciseLimits", cascade={"persist"})
   */
  protected $exerciseLimits;

  /**
   * Get exercise limits based on environment.
   * @param RuntimeEnvironment $environment
   * @return ExerciseLimits[]
   */
  public function getLimitsByEnvironment(RuntimeEnvironment $environment): array {
    $result = $this->exerciseLimits->filter(
      function (ExerciseLimits $exerciseLimits) use ($environment) {
        return $exerciseLimits->getRuntimeEnvironment()->getId() === $environment->getId();
      });
    return $result->getValues();
  }

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
   * @ORM\ManyToMany(targetEntity="ExerciseEnvironmentConfig", cascade={"persist"})
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
   * @ORM\ManyToOne(targetEntity="ExerciseConfig")
   */
  protected $exerciseConfig;

  public function getExerciseConfig() {
    return $this->exerciseConfig;
  }

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $scoreCalculator;

  public function getScoreCalculator(): ?string {
    return $this->scoreCalculator;
  }

  /**
   * @ORM\Column(type="text")
   */
  protected $scoreConfig;

  public function getScoreConfig(): string {
    return $this->scoreConfig;
  }

  /**
   * @ORM\ManyToMany(targetEntity="ExerciseTest", cascade={"persist"})
   * @var Collection|Selectable
   */
  protected $exerciseTests;

  public function getExerciseTests(): Collection {
    return $this->exerciseTests;
  }

  /**
   * Get exercise tests based on given test name.
   * @param string $name
   * @return ExerciseTest|null
   */
  public function getExerciseTestByName(string $name): ?ExerciseTest {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("name", $name));
    $first = $this->exerciseTests->matching($criteria)->first();
    return $first === false ? null : $first;
  }
}
