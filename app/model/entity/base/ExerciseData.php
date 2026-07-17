<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;

trait ExerciseData
{
    #[ORM\Column(type: 'string', options: ['default' => 'simpleExerciseConfig'])]
    protected $configurationType;

    public function getConfigurationType(): string
    {
        return $this->configurationType;
    }

    /**
     * @var Collection<LocalizedExercise>|Selectable
     */
    #[ORM\ManyToMany(targetEntity: LocalizedExercise::class, indexBy: 'locale')]
    protected $localizedTexts;

    public function getLocalizedTexts(): Collection
    {
        return $this->localizedTexts;
    }

    /**
     * Return all localized texts as an array indexed by locales.
     * @return array
     */
    public function getLocalizedTextsAssocArray(): array
    {
        $result = [];
        foreach ($this->getLocalizedTexts() as $text) {
            /** @var LocalizedExercise $text */
            $result[$text->getLocale()] = $text;
        }
        return $result;
    }

    public function addLocalizedText(LocalizedExercise $localizedText)
    {
        $this->localizedTexts->add($localizedText);
    }

    /**
     * Get localized text based on given locale.
     * @param string $locale
     * @return LocalizedExercise|null
     */
    public function getLocalizedTextByLocale(string $locale): ?LocalizedEntity
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
        $first = $this->localizedTexts->matching($criteria)->first();
        return $first === false ? null : $first;
    }

    /**
     * @var Collection
     */
    #[ORM\ManyToMany(targetEntity: RuntimeEnvironment::class)]
    protected $runtimeEnvironments;

    /**
     * Get all runtime environments associated with the object
     * @return ReadableCollection
     */
    public function getRuntimeEnvironments(): ReadableCollection
    {
        return $this->runtimeEnvironments;
    }

    /**
     * Get IDs of all available runtime environments
     * @return array
     */
    public function getRuntimeEnvironmentsIds()
    {
        return $this->getRuntimeEnvironments()->map(
            function (RuntimeEnvironment $environment) {
                return $environment->getId();
            }
        )->getValues();
    }

    /**
     * @var Collection
     */
    #[ORM\ManyToMany(targetEntity: HardwareGroup::class)]
    protected $hardwareGroups;

    /**
     * @return Collection|HardwareGroup[]
     */
    public function getHardwareGroups(): Collection
    {
        return $this->hardwareGroups;
    }

    /**
     * Get IDs of all defined hardware groups.
     * @return string[]
     */
    public function getHardwareGroupsIds()
    {
        return $this->hardwareGroups->map(
            function (HardwareGroup $group) {
                return $group->getId();
            }
        )->getValues();
    }

    /**
     * @var Collection
     */
    #[ORM\ManyToMany(targetEntity: ExerciseLimits::class, cascade: ['persist'])]
    protected $exerciseLimits;

    /**
     * Get collection of limits belonging to exercise.
     * @return Collection
     */
    public function getExerciseLimits(): Collection
    {
        return $this->exerciseLimits;
    }

    /**
     * Get exercise limits based on environment.
     * @param RuntimeEnvironment $environment
     * @return ExerciseLimits[]
     */
    public function getLimitsByEnvironment(RuntimeEnvironment $environment): array
    {
        $result = $this->exerciseLimits->filter(
            function (ExerciseLimits $exerciseLimits) use ($environment) {
                return $exerciseLimits->getRuntimeEnvironment()->getId() === $environment->getId();
            }
        );
        return $result->getValues();
    }

    /**
     * Get exercise limits based on environment and hardware group.
     * @param RuntimeEnvironment $environment
     * @param HardwareGroup $hwGroup
     * @return ExerciseLimits|null
     */
    public function getLimitsByEnvironmentAndHwGroup(
        RuntimeEnvironment $environment,
        HardwareGroup $hwGroup
    ): ?ExerciseLimits {
        $first = $this->exerciseLimits->filter(
            function (ExerciseLimits $exerciseLimits) use ($environment, $hwGroup) {
                return $exerciseLimits->getRuntimeEnvironment()->getId() === $environment->getId()
                    && $exerciseLimits->getHardwareGroup()->getId() === $hwGroup->getId();
            }
        )->first();
        return $first === false ? null : $first;
    }

    /**
     * @var Collection|Selectable
     */
    #[ORM\ManyToMany(targetEntity: ExerciseEnvironmentConfig::class, cascade: ['persist'])]
    protected $exerciseEnvironmentConfigs;

    /**
     * Get collection of environment configs belonging to exercise.
     * @return Collection
     */
    public function getExerciseEnvironmentConfigs(): Collection
    {
        return $this->exerciseEnvironmentConfigs;
    }

    /**
     * Get runtime configuration based on environment identification.
     * @param RuntimeEnvironment $environment
     * @return ExerciseEnvironmentConfig|null
     */
    public function getExerciseEnvironmentConfigByEnvironment(
        RuntimeEnvironment $environment
    ): ?ExerciseEnvironmentConfig {
        $first = $this->exerciseEnvironmentConfigs->filter(
            function (ExerciseEnvironmentConfig $runtimeConfig) use ($environment) {
                return $runtimeConfig->getRuntimeEnvironment()->getId() === $environment->getId();
            }
        )->first();
        return $first === false ? null : $first;
    }

    #[ORM\ManyToOne(targetEntity: ExerciseConfig::class, cascade: ['persist'])]
    protected $exerciseConfig;

    public function getExerciseConfig(): ?ExerciseConfig
    {
        return $this->exerciseConfig;
    }

    #[ORM\ManyToOne(targetEntity: ExerciseScoreConfig::class, cascade: ['persist'])]
    protected $scoreConfig;

    public function getScoreConfig(): ExerciseScoreConfig
    {
        return $this->scoreConfig;
    }

    /**
     * @var Collection|Selectable
     */
    #[ORM\ManyToMany(targetEntity: ExerciseTest::class, cascade: ['persist'])]
    protected $exerciseTests;

    public function getExerciseTests(): Collection
    {
        return $this->exerciseTests;
    }

    /**
     * Get exercise tests based on given test identification.
     * @param int $id
     * @return ExerciseTest|null
     */
    public function getExerciseTestById(int $id): ?ExerciseTest
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq("id", $id));
        $first = $this->exerciseTests->matching($criteria)->first();
        return $first === false ? null : $first;
    }

    /**
     * Get exercise tests based on given test name.
     * @param string $name
     * @return ExerciseTest|null
     */
    public function getExerciseTestByName(string $name): ?ExerciseTest
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq("name", $name));
        $first = $this->exerciseTests->matching($criteria)->first();
        return $first === false ? null : $first;
    }

    /**
     * Get tests indexed by entity id and containing actual test name.
     * @return string[]
     */
    public function getExerciseTestsNames(): array
    {
        $tests = [];
        foreach ($this->exerciseTests as $exerciseTest) {
            $tests[$exerciseTest->getId()] = $exerciseTest->getName();
        }
        return $tests;
    }

    /**
     * Get identifications of exercise tests.
     * @return array
     */
    public function getExerciseTestsIds()
    {
        return $this->exerciseTests->map(
            function (ExerciseTest $test) {
                return $test->getId();
            }
        )->getValues();
    }

    /**
     * @var Collection<ExerciseFile>
     */
    #[ORM\ManyToMany(targetEntity: ExerciseFile::class)]
    protected $exerciseFiles;

    public function getExerciseFiles(): Collection
    {
        return $this->exerciseFiles;
    }

    public function addExerciseFile(ExerciseFile $exerciseFile)
    {
        $this->exerciseFiles->add($exerciseFile);
    }

    /**
     * @param ExerciseFile $file
     * @return bool
     */
    public function removeExerciseFile(ExerciseFile $file)
    {
        return $this->exerciseFiles->removeElement($file);
    }

    /**
     * Get identifications of exercise files.
     * @return array
     */
    public function getExerciseFilesIds()
    {
        return $this->exerciseFiles->map(
            function (ExerciseFile $file) {
                return $file->getId();
            }
        )->getValues();
    }

    public function getHashedExerciseFiles(): array
    {
        $files = [];
        /** @var ExerciseFile $file */
        foreach ($this->exerciseFiles as $file) {
            $files[$file->getName()] = $file->getHashName();
        }
        return $files;
    }

    /**
     * @var Collection<AttachmentFile>
     */
    #[ORM\ManyToMany(targetEntity: AttachmentFile::class)]
    protected $attachmentFiles;

    public function getAttachmentFiles(): Collection
    {
        return $this->attachmentFiles;
    }

    public function addAttachmentFile(AttachmentFile $exerciseFile)
    {
        $this->attachmentFiles->add($exerciseFile);
    }

    /**
     * @param AttachmentFile $file
     * @return bool
     */
    public function removeAttachmentFile(AttachmentFile $file): bool
    {
        return $this->attachmentFiles->removeElement($file);
    }

    /**
     * Get identifications of additional exercise files.
     * @return string[]
     */
    public function getAttachmentFilesIds(): array
    {
        return $this->attachmentFiles->map(
            function (AttachmentFile $file) {
                return $file->getId();
            }
        )->getValues();
    }

    /**
     * How many files may one submit in a solution.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    protected $solutionFilesLimit = null;

    public function getSolutionFilesLimit(): ?int
    {
        return $this->solutionFilesLimit;
    }

    public function setSolutionFilesLimit(?int $filesLimit)
    {
        $this->solutionFilesLimit = $filesLimit;
    }

    /**
     * Maximal allowed size (in bytes) of all files submitted for a solution.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    protected $solutionSizeLimit = null;

    public function getSolutionSizeLimit(): ?int
    {
        return $this->solutionSizeLimit;
    }

    public function setSolutionSizeLimit(?int $sizeLimit)
    {
        $this->solutionSizeLimit = $sizeLimit;
    }
}
