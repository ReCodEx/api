<?php

namespace App\Model\Entity;

use App\Exceptions\ExerciseConfigException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use DateTime;
use JsonSerializable;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getName()
 * @method string getDescription()
 * @method User getAuthor()
 * @method PipelineConfig getPipelineConfig()
 * @method int getVersion()
 * @method setName(string $name)
 * @method setDescription(string $description)
 * @method setPipelineConfig($config)
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
   * @ORM\ManyToOne(targetEntity="Pipeline")
   */
  protected $createdFrom;

  /**
   * Constructor
   * @param string $name
   * @param int $version
   * @param string $description
   * @param PipelineConfig $pipelineConfig
   * @param User $author
   * @param ExerciseConfig|null $createdFrom
   */
  private function __construct(string $name, int $version, string $description,
      PipelineConfig $pipelineConfig, User $author,
      ExerciseConfig $createdFrom = null) {
    $this->createdAt = new DateTime;

    $this->name = $name;
    $this->version = $version;
    $this->description = $description;
    $this->pipelineConfig = $pipelineConfig;
    $this->author = $author;
    $this->createdFrom = $createdFrom;
  }

  /**
   * Create empty pipeline entity.
   * @param User $user
   * @return Pipeline
   */
  public static function create(User $user): Pipeline {
    return new self("", 1, "", new PipelineConfig((string) new \App\Helpers\ExerciseConfig\Pipeline, $user), $user);
  }

  /**
   * Fork pipeline entity into new one.
   * @param User $user
   * @param Pipeline $pipeline
   * @return Pipeline
   */
  public static function forkFrom(User $user, Pipeline $pipeline): Pipeline {
    return new self(
      $pipeline->getName(),
      $pipeline->getVersion(),
      $pipeline->getDescription(),
      $pipeline->getPipelineConfig(),
      $user,
      $pipeline
    );
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "version" => $this->version,
      "description" => $this->description,
      "author" => $this->author->getId(),
      "pipeline" => $this->pipelineConfig->getParsedPipeline()
    ];
  }
}
