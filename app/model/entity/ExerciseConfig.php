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
 * @method string getId()
 */
class ExerciseConfig
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
  protected $config;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\ManyToOne(targetEntity="ExerciseConfig")
   */
  protected $createdFrom;

  /**
   * @ORM\OneToMany(targetEntity="Exercise", mappedBy="exerciseConfig")
   */
  protected $exercises;

  /**
   * Constructor
   * @param string $config
   * @param ExerciseConfig|null $createdFrom
   */
  public function __construct(string $config, ?ExerciseConfig $createdFrom = NULL) {
    $this->exercises = new ArrayCollection();
    $this->createdAt = new DateTime;

    $this->config = $config;
    $this->createdFrom = $createdFrom;
  }

  /**
   * Return array-like structure containing config.
   * @return array|string
   * @throws ExerciseConfigException
   */
  public function getParsedConfig() {
    try {
      return Yaml::parse($this->config);
    } catch (ParseException $e) {
      throw new ExerciseConfigException("Exercise configuration is not a valid YAML and it cannot be parsed.");
    }
  }

}
