<?php
namespace App\Helpers;
use Nette;
use Nette\Utils\Arrays;

class AssignmentRestrictionsConfig {
  use Nette\SmartObject;

  private $submissionsCountLimitLimit;

  private $maxPointsLimit;

  public function __construct(array $config) {
    $this->submissionsCountLimitLimit = Arrays::get($config, "submissionsCountLimitLimit", 100);
    $this->maxPointsLimit = Arrays::get($config, "maxPointsLimit", 10000);
  }

  public function getSubmissionsCountLimitLimit(): int {
    return $this->submissionsCountLimitLimit;
  }

  public function getMaxPointsLimit(): int {
    return $this->maxPointsLimit;
  }
}
