<?php

namespace App\Model\Entity;

use App\Exceptions\ExerciseConfigException;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use DateTime;


/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method User getAuthor()
 */
class PipelineConfig
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="text")
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
   * @ORM\ManyToOne(targetEntity="PipelineConfig")
   */
  protected $createdFrom;

  /**
   * @ORM\OneToMany(targetEntity="Pipeline", mappedBy="pipelineConfig")
   */
  protected $pipelines;

  /**
   * Constructor
   * @param string $pipeline
   * @param User $author
   * @param PipelineConfig|null $createdFrom
   */
  public function __construct(string $pipeline, User $author,
      PipelineConfig $createdFrom = NULL) {
    $this->createdAt = new DateTime;
    $this->pipelines = new ArrayCollection;

    $this->pipelineConfig = $pipeline;
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
      return Yaml::parse($this->pipelineConfig);
    } catch (ParseException $e) {
      throw new ExerciseConfigException("Pipeline is not a valid YAML and it cannot be parsed.");
    }
  }

}
