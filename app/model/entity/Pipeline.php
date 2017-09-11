<?php

namespace App\Model\Entity;

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
 * @method setName(string $name)
 * @method setDescription(string $description)
 * @method setPipelineConfig($config)
 * @method void setUpdatedAt(DateTime $date)
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
   * Constructor
   * @param string $name
   * @param int $version
   * @param string $description
   * @param PipelineConfig $pipelineConfig
   * @param User $author
   * @param ExerciseConfig|null $createdFrom
   * @param Exercise|null $exercise
   */
  private function __construct(string $name, int $version, string $description,
      PipelineConfig $pipelineConfig, User $author,
      ExerciseConfig $createdFrom = null, Exercise $exercise = null) {
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;

    $this->name = $name;
    $this->version = $version;
    $this->description = $description;
    $this->pipelineConfig = $pipelineConfig;
    $this->author = $author;
    $this->createdFrom = $createdFrom;
    $this->exercise = $exercise;
  }

  /**
   * Create empty pipeline entity.
   * @param User $user
   * @return Pipeline
   */
  public static function create(User $user): Pipeline {
    return new self(
      "",
      1,
      "",
      new PipelineConfig((string) new \App\Helpers\ExerciseConfig\Pipeline, $user),
      $user
    );
  }

  /**
   * Fork pipeline entity into new one which belongs to given exercise.
   * @param User $user
   * @param Pipeline $pipeline
   * @param Exercise $exercise
   * @return Pipeline
   */
  public static function forkFrom(User $user, Pipeline $pipeline,
      Exercise $exercise): Pipeline {
    return new self(
      $pipeline->getName(),
      $pipeline->getVersion(),
      $pipeline->getDescription(),
      $pipeline->getPipelineConfig(),
      $user,
      $pipeline,
      $exercise
    );
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
      "pipeline" => $this->pipelineConfig->getParsedPipeline()
    ];
  }
}
