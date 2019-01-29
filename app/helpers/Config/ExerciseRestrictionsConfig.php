<?php
namespace App\Helpers;
use Nette;
use Nette\Utils\Arrays;

class ExerciseRestrictionsConfig {
  use Nette\SmartObject;

  private $testCountLimit;

  private $supplementaryFileCountLimit;

  private $supplementaryFileSizeLimit;

  public function __construct(array $config) {
    $this->testCountLimit = Arrays::get($config, "testCountLimit", 100);
    $this->supplementaryFileCountLimit = Arrays::get($config, "supplementaryFileCountLimit", 200);
    $this->supplementaryFileSizeLimit = Arrays::get($config, "supplementaryFileSizeLimit", 256 * 1024 * 1024);
  }

  public function getTestCountLimit(): int {
    return $this->testCountLimit;
  }

  public function getSupplementaryFileCountLimit(): int {
    return $this->supplementaryFileCountLimit;
  }

  public function getSupplementaryFileSizeLimit() {
    return $this->supplementaryFileSizeLimit;
  }
}
