<?php

namespace App\Model\Entity;

use App\Exceptions\ExerciseConfigException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method RuntimeEnvironment getRuntimeEnvironment()
 */
class RuntimeConfig
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="RuntimeEnvironment")
   */
  protected $runtimeEnvironment;

  /**
   * @ORM\Column(type="string")
   */
  protected $variablesTable;

  /**
   * @ORM\ManyToMany(targetEntity="Exercise", mappedBy="runtimeConfigs")
   */
  protected $exercises;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * Created from.
   * @ORM\ManyToOne(targetEntity="RuntimeConfig")
   * @var RuntimeConfig
   */
  protected $createdFrom;

  /**
   * RuntimeConfig constructor.
   * @param RuntimeEnvironment $runtimeEnvironment
   * @param string $variablesTable
   * @param RuntimeConfig|null $createdFrom
   */
  public function __construct(
    RuntimeEnvironment $runtimeEnvironment,
    string $variablesTable,
    RuntimeConfig $createdFrom = null
  ) {
    $this->runtimeEnvironment = $runtimeEnvironment;
    $this->variablesTable = $variablesTable;
    $this->createdFrom = $createdFrom;
    $this->createdAt = new \DateTime;
    $this->exercises = new ArrayCollection;
  }

  /**
   * Return array-like structure containing variables table.
   * @return array
   * @throws ExerciseConfigException
   */
  public function getParsedVariablesTable(): array {
    try {
      return Yaml::parse($this->variablesTable);
    } catch (ParseException $e) {
      throw new ExerciseConfigException("Variables table is not a valid YAML and it cannot be parsed.");
    }
  }

}
