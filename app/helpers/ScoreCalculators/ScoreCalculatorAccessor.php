<?php
namespace App\Helpers;
use App\Exceptions\InvalidArgumentException;
use Nette;
use Nette\Utils\Arrays;

/**
 * Provides access to different implementations of score calculation
 */
class ScoreCalculatorAccessor extends Nette\Object
{
  private $calculators;

  public function __construct(array $calculators)
  {
    if (count($calculators) === 0) {
      throw new InvalidArgumentException("No score calculators provided");
    }

    $this->calculators = $calculators;
  }

  public function getCalculator(string $name): IScoreCalculator
  {
    if (!$name) {
      return $this->getDefaultCalculator();
    }

    return Arrays::get($this->calculators, $name, NULL);
  }

  public function getDefaultCalculator(): IScoreCalculator
  {
    return reset($this->calculators);
  }
}