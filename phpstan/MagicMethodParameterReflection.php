<?php
namespace App\PHPStan;

use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\Type;
use PHPStan;

class MagicMethodParameterReflection implements ParameterReflection
{
  /** @var Type */
  private $type;

  /** @var string */
  private $name;

  public function __construct(string $name, Type $type)
  {
    $this->name = $name;
    $this->type = $type;
  }

  public function getName(): string
  {
    return $this->name;
  }

  public function isOptional(): bool
  {
    return FALSE;
  }

  public function getType(): Type
  {
    return $this->type;
  }

  public function isPassedByReference(): bool
  {
    return FALSE;
  }
}