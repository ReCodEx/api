<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class TaskResult implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

  public function __construct(string $taskName, float $usedTime, int $usedMemory,
      string $output, TestResult $result) {
    $this->taskName = $taskName;
    $this->usedTime = $usedTime;
    $this->usedMemory = $usedMemory;
    $this->output = $output;
    $this->testResult = $result;
  }

  /**
    * @ORM\Id
    * @ORM\Column(type="guid")
    * @ORM\GeneratedValue(strategy="UUID")
    */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $taskName;

  /**
   * @ORM\Column(type="float")
   */
  protected $usedTime;

  /**
   * @ORM\Column(type="integer")
   */
  protected $usedMemory;

  /**
   * @ORM\Column(type="text")
   */
  protected $output;

  /**
   * @ORM\ManyToOne(targetEntity="TestResult", inversedBy="tasks")
   */
  protected $testResult;

  public function jsonSerialize() {
    return [
      "id" => $this->taskName,
      "usedTime" => $this->usedTime,
      "usedMemory" => $this->usedMemory,
      "output" => $this->output
    ];
  }

}
