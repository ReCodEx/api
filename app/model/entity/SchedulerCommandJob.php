<?php

namespace App\Model\Entity;

use App\Exceptions\ApiException;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @ORM\Entity
 * @method string getCommand()
 * @method string getArguments()
 */
class SchedulerCommandJob extends SchedulerJob {

  /**
   * @ORM\Column(type="string")
   */
  protected $command;

  /**
   * Arguments of command encoded in yaml.
   * @ORM\Column(type="text")
   */
  protected $arguments;


  public function __construct(DateTime $nextExecution, int $delay, string $command, string $arguments) {
    parent::__construct($nextExecution, $delay);
    $this->command = $command;
    $this->arguments = $arguments;
  }


  public function getDecodedArguments() {
    try {
      return Yaml::parse($this->arguments);
    } catch (ParseException $e) {
      throw new ApiException("Yaml cannot be parsed: " . $e->getMessage());
    }
  }
}
