<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use Symfony\Component\Yaml\Yaml;


/**
 * High-level configuration port holder.
 */
class Port {

  /**
   * Port identification.
   * @var string
   */
  protected $name = null;

  /**
   * Bound variable for this port.
   * @var string
   */
  protected $variable = null;


  /**
   * Get name of this port.
   * @return null|string
   */
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * Set name of this port.
   * @param string $name
   * @return Port
   */
  public function setName(string $name): Port {
    $this->name = $name;
    return $this;
  }

  /**
   * Get variable bounded to this port.
   * @return null|string
   */
  public function getVariable(): ?string {
    return $this->variable;
  }

  /**
   * Set variable bounded to this port.
   * @param string $variable
   * @return Port
   */
  public function setVariable(string $variable): Port {
    $this->variable = $variable;
    return $this;
  }

}
