<?php

namespace App\Model\Entity;

use App\Exceptions\ExerciseConfigException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use DateTime;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getName()
 * @method User getAuthor()
 */
class Pipeline
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
   * @ORM\Column(type="text")
   */
  protected $pipeline;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $author;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\ManyToOne(targetEntity="ExerciseConfig")
   */
  protected $createdFrom;

  /**
   * Constructor
   * @param string $name
   * @param string $pipeline
   * @param User $author
   * @param ExerciseConfig|null $createdFrom
   */
  public function __construct(string $name, string $pipeline, User $author,
      ExerciseConfig $createdFrom = NULL) {
    $this->createdAt = new DateTime;

    $this->name = $name;
    $this->pipeline = $pipeline;
    $this->author = $author;
    $this->createdFrom = $createdFrom;
  }

  /**
   * Return array-like structure containing pipeline.
   * @return array|string
   * @throws ExerciseConfigException
   */
  public function getParsedPipeline() {
    try {
      return Yaml::parse($this->pipeline);
    } catch (ParseException $e) {
      throw new ExerciseConfigException("Pipeline is not a valid YAML and it cannot be parsed.");
    }
  }

}
