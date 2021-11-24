<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidArgumentException;
use App\Helpers\ExerciseConfig\Pipeline as ExerciseConfigPipeline;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class Pipeline
{
    use CreateableEntity;
    use UpdateableEntity;
    use DeleteableEntity;
    use VersionableEntity;

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
     * @ORM\Column(type="text")
     */
    protected $description;

    /**
     * @ORM\ManyToOne(targetEntity="PipelineConfig", inversedBy="pipelines", cascade={"persist"})
     */
    protected $pipelineConfig;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * If the pipeline has no author set, it is treated as predefined global pipeline used by everyone.
     */
    protected $author;

    public function getAuthor(): ?User
    {
        return $this->author && $this->author->isDeleted() ? null : $this->author;
    }

    /**
     * If the author is set to null, the pipeline is turned into a global pipeline.
     * @param User|null $author
     */
    public function setAuthor(?User $author = null): void
    {
        $this->author = $author;
    }

    public function isGlobal(): bool
    {
        return $this->getAuthor() === null;
    }

    /**
     * @ORM\ManyToOne(targetEntity="Pipeline")
     */
    protected $createdFrom;

    public function getCreatedFrom(): ?Pipeline
    {
        return $this->createdFrom && $this->createdFrom->isDeleted() ? null : $this->createdFrom;
    }

    /**
     * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="pipelines")
     */
    protected $exercises;

    /**
     * @ORM\ManyToMany(targetEntity="SupplementaryExerciseFile", inversedBy="pipelines")
     * @var Collection
     */
    protected $supplementaryEvaluationFiles;

    /**
     * @ORM\OneToMany(targetEntity="PipelineParameter", mappedBy="pipeline", indexBy="name",
     *                cascade={"persist"}, orphanRemoval=true)
     * @var Collection
     */
    protected $parameters;

    /**
     * @ORM\ManyToMany(targetEntity="RuntimeEnvironment")
     */
    protected $runtimeEnvironments;

    public const DEFAULT_PARAMETERS = [
        "isCompilationPipeline" => false,
        "isExecutionPipeline" => false,
        "judgeOnlyPipeline" => false,
        "producesStdout" => false,
        "producesFiles" => false,
        "hasEntryPoint" => false,
        "hasExtraFiles" => false,
    ];

    /**
     * Pipeline constructor.
     * @param string $name
     * @param int $version
     * @param string $description
     * @param PipelineConfig $pipelineConfig
     * @param Collection $supplementaryEvaluationFiles
     * @param User $author
     * @param Pipeline|null $createdFrom
     * @param Exercise|null $exercise Initial exercise to which the pipeline belongs to.
     * @param Collection|null $runtimeEnvironments
     * @throws Exception
     */
    private function __construct(
        string $name,
        int $version,
        string $description,
        PipelineConfig $pipelineConfig,
        Collection $supplementaryEvaluationFiles,
        ?User $author = null,
        ?Pipeline $createdFrom = null,
        Exercise $exercise = null,
        Collection $runtimeEnvironments = null
    ) {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();

        $this->name = $name;
        $this->version = $version;
        $this->description = $description;
        $this->pipelineConfig = $pipelineConfig;
        $this->author = $author;
        $this->createdFrom = $createdFrom;
        $this->exercises = new ArrayCollection();
        $this->supplementaryEvaluationFiles = $supplementaryEvaluationFiles;
        $this->parameters = new ArrayCollection();
        $this->runtimeEnvironments = new ArrayCollection();
        if ($runtimeEnvironments) {
            foreach ($runtimeEnvironments as $runtimeEnvironment) {
                $this->runtimeEnvironments->add($runtimeEnvironment);
            }
        }
        if ($exercise) {
            $exercise->addPipeline($this);
        }
    }

    /**
     * Get filtered collection of not-deleted exercises.
     * @return ArrayCollection
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
     * Get filtered collection of all exercises including delted ones.
     * @return ArrayCollection
     */
    public function getAllExercises()
    {
        return $this->exercises;
    }

    /**
     * Get array of identifications of exercises using this pipeline.
     * @return array
     */
    public function getExercisesIds()
    {
        return $this->getExercises()->map(
            function (Exercise $exercise) {
                return $exercise->getId();
            }
        )->getValues();
    }

    public function getSupplementaryEvaluationFiles(): Collection
    {
        return $this->supplementaryEvaluationFiles;
    }

    /**
     * Add supplementary file which should be accessible within pipeline.
     * @param SupplementaryExerciseFile $exerciseFile
     */
    public function addSupplementaryEvaluationFile(SupplementaryExerciseFile $exerciseFile)
    {
        $this->supplementaryEvaluationFiles->add($exerciseFile);
    }

    /**
     * Get array of identifications of supplementary files
     * @return array
     */
    public function getSupplementaryFilesIds()
    {
        return $this->supplementaryEvaluationFiles->map(
            function (SupplementaryExerciseFile $file) {
                return $file->getId();
            }
        )->getValues();
    }

    /**
     * Get array containing hashes of files indexed by the name.
     * @return array
     */
    public function getHashedSupplementaryFiles(): array
    {
        $files = [];
        /** @var SupplementaryExerciseFile $file */
        foreach ($this->supplementaryEvaluationFiles as $file) {
            $files[$file->getName()] = $file->getHashName();
        }
        return $files;
    }

    public function addRuntimeEnvironment(RuntimeEnvironment $environment): void
    {
        $this->runtimeEnvironments->add($environment);
    }

    public function removeRuntimeEnvironment(?RuntimeEnvironment $environment): void
    {
        $this->runtimeEnvironments->remove($environment);
    }

    /**
     * Set completely new associations with runtime environments.
     * @param RuntimeEnvironment[] $environments list of runtime environments to override current associatons
     */
    public function setRuntimeEnvironments(array $environments): void
    {
        $this->runtimeEnvironments->clear();
        foreach ($environments as $environment) {
            $this->addRuntimeEnvironment($environment);
        }
    }


    /**
     * Create empty pipeline entity.
     * @param User|null $user The author of the pipeline (null for universal pipelines).
     * @param Exercise|null $exercise Initial exercise to which the pipeline belongs to.
     * @return Pipeline
     * @throws Exception
     */
    public static function create(?User $user, ?Exercise $exercise = null): Pipeline
    {
        return new self(
            "",
            1,
            "",
            new PipelineConfig((string)(new ExerciseConfigPipeline()), $user),
            new ArrayCollection(),
            $user,
            null,
            $exercise
        );
    }

    /**
     * Fork pipeline entity into new one.
     * @param User|null $user
     * @param Pipeline $pipeline
     * @param Exercise|null $exercise Initial exercise to which the pipeline belongs to.
     * @return Pipeline
     * @throws Exception
     */
    public static function forkFrom(?User $user, Pipeline $pipeline, Exercise $exercise = null): Pipeline
    {
        return new self(
            $pipeline->getName(),
            $pipeline->getVersion(),
            $pipeline->getDescription(),
            $pipeline->getPipelineConfig(),
            $pipeline->getSupplementaryEvaluationFiles(),
            $user,
            $pipeline,
            $exercise
        );
    }

    public function setParameters($parameters)
    {
        foreach ($parameters as $name => $value) {
            if (!array_key_exists($name, static::DEFAULT_PARAMETERS)) {
                throw new InvalidArgumentException(sprintf("Unknown parameter %s", $name));
            }

            if ($this->parameters->containsKey($name)) {
                $parameter = $this->parameters->get($name);
            } else {
                $default = static::DEFAULT_PARAMETERS[$name];

                if (is_bool($default)) {
                    $parameter = new BooleanPipelineParameter($this, $name);
                } else {
                    if (is_string($default)) {
                        $parameter = new StringPipelineParameter($this, $name);
                    } else {
                        throw new InvalidArgumentException(sprintf("Unsupported value type for parameter %s", $name));
                    }
                }

                $this->parameters[$name] = $parameter;
            }

            if ($value !== static::DEFAULT_PARAMETERS[$name]) {
                $parameter->setValue($value);
            } else {
                $this->parameters->remove($name);
            }
        }

        foreach ($this->parameters->getKeys() as $key) {
            if (!array_key_exists($key, $parameters)) {
                unset($this->parameters[$key]);
            }
        }
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getPipelineConfig(): PipelineConfig
    {
        return $this->pipelineConfig;
    }

    public function setPipelineConfig(PipelineConfig $pipelineConfig): void
    {
        $this->pipelineConfig = $pipelineConfig;
    }

    public function getParameters(): Collection
    {
        return $this->parameters;
    }

    public function getRuntimeEnvironments(): Collection
    {
        return $this->runtimeEnvironments;
    }
}
