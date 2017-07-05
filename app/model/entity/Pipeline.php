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
 * @method User getAuthor()
 * @method PipelineConfig getPipelineConfig()
 * @method setName($name)
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
   * @param PipelineConfig $pipelineConfig
   * @param User $author
   * @param ExerciseConfig|null $createdFrom
   */
  private function __construct(string $name, PipelineConfig $pipelineConfig,
      User $author, ExerciseConfig $createdFrom = null) {
    $this->createdAt = new DateTime;

    $this->name = $name;
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
    return new self("", new PipelineConfig((string) new \App\Helpers\ExerciseConfig\Pipeline, $user), $user);
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
      $pipeline->getPipelineConfig(),
      $user,
      $pipeline
    );
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "author" => $this->author->getId(),
      "pipeline" => $this->pipelineConfig->getParsedPipeline()
    ];
  }
}
