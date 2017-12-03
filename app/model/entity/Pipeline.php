<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 * @method string getId()
 * @method string getName()
 * @method string getDescription()
 * @method User getAuthor()
 * @method PipelineConfig getPipelineConfig()
 * @method int getVersion()
 * @method Exercise getExercise()
 * @method DateTime getDeletedAt()
 * @method ArrayCollection getSupplementaryEvaluationFiles()
 * @method setName(string $name)
 * @method setDescription(string $description)
 * @method setPipelineConfig($config)
 * @method void setUpdatedAt(DateTime $date)
 * @method PipelineParameter[] getParameters()
 * @method Collection getRuntimeEnvironments()
 */
class Pipeline implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

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
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\ManyToOne(targetEntity="PipelineConfig", inversedBy="pipelines", cascade={"persist"})
   */
  protected $pipelineConfig;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $author;

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
   * @ORM\ManyToOne(targetEntity="Pipeline")
   */
  protected $createdFrom;

  /**
   * @ORM\ManyToOne(targetEntity="Exercise", inversedBy="pipelines")
   */
  protected $exercise;

  /**
   * @ORM\ManyToMany(targetEntity="SupplementaryExerciseFile", inversedBy="pipelines")
   */
  protected $supplementaryEvaluationFiles;

  /**
   * @ORM\OneToMany(targetEntity="PipelineParameter", mappedBy="pipeline", indexBy="name", cascade={"persist"}, orphanRemoval=true)
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
    "producesStdout" => false,
    "producesFiles" => false,
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
   * @param Exercise|null $exercise
   * @param Collection|null $runtimeEnvironments
   */
  private function __construct(string $name, int $version, string $description,
      PipelineConfig $pipelineConfig, Collection $supplementaryEvaluationFiles,
      User $author, ?Pipeline $createdFrom = null, ?Exercise $exercise = null, ?Collection $runtimeEnvironments = null) {
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;

    $this->name = $name;
    $this->version = $version;
    $this->description = $description;
    $this->pipelineConfig = $pipelineConfig;
    $this->author = $author;
    $this->createdFrom = $createdFrom;
    $this->exercise = $exercise;
    $this->supplementaryEvaluationFiles = $supplementaryEvaluationFiles;
    $this->parameters = new ArrayCollection();
    $this->runtimeEnvironments = new ArrayCollection();
    if ($runtimeEnvironments) {
      foreach ($runtimeEnvironments as $runtimeEnvironment) {
        $this->runtimeEnvironments->add($runtimeEnvironment);
      }
    }
  }

  /**
   * Add supplementary file which should be accessible within pipeline.
   * @param SupplementaryExerciseFile $exerciseFile
   */
  public function addSupplementaryEvaluationFile(SupplementaryExerciseFile $exerciseFile) {
    $this->supplementaryEvaluationFiles->add($exerciseFile);
  }

  public function addRuntimeEnvironment(RuntimeEnvironment $environment) {
    $this->runtimeEnvironments->add($environment);
  }

  /**
   * Get array of identifications of supplementary files
   * @return array
   */
  public function getSupplementaryFilesIds() {
    return $this->supplementaryEvaluationFiles->map(
      function(SupplementaryExerciseFile $file) {
        return $file->getId();
      })->getValues();
  }

  /**
   * Get array containing hashes of files indexed by the name.
   * @return array
   */
  public function getHashedSupplementaryFiles(): array {
    $files = [];
    /** @var SupplementaryExerciseFile $file */
    foreach ($this->supplementaryEvaluationFiles as $file) {
      $files[$file->getName()] = $file->getHashName();
    }
    return $files;
  }


  /**
   * Create empty pipeline entity.
   * @param User $user
   * @param Exercise|null $exercise
   * @return Pipeline
   */
  public static function create(User $user, Exercise $exercise = null): Pipeline {
    return new self(
      "",
      1,
      "",
      new PipelineConfig((string) new \App\Helpers\ExerciseConfig\Pipeline, $user),
      new ArrayCollection,
      $user,
      null,
      $exercise
    );
  }

  /**
   * Fork pipeline entity into new one which belongs to given exercise.
   * @param User $user
   * @param Pipeline $pipeline
   * @param Exercise|null $exercise
   * @return Pipeline
   */
  public static function forkFrom(User $user, Pipeline $pipeline,
      ?Exercise $exercise): Pipeline {
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

  public function setParameters($parameters) {
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
        } else if (is_string($default)) {
          $parameter = new StringPipelineParameter($this, $name);
        } else {
          throw new InvalidArgumentException(sprintf("Unsupported value type for parameter %s", $name));
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

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "version" => $this->version,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "description" => $this->description,
      "author" => $this->author->getId(),
      "exerciseId" => $this->exercise ? $this->exercise->getId() : null,
      "supplementaryFilesIds" => $this->getSupplementaryFilesIds(),
      "pipeline" => $this->pipelineConfig->getParsedPipeline(),
      "parameters" => array_merge(static::DEFAULT_PARAMETERS, $this->parameters->toArray()),
      "runtimeEnvironmentIds" => $this->runtimeEnvironments->map(function (RuntimeEnvironment $env) {
        return $env->getId();
      })
    ];
  }
}
