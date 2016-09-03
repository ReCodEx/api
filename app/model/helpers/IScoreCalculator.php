<?php

namespace App\Model\Helpers;

interface IScoreCalculator {
  public function computeScore(array $testResults);
  public static function isScoreConfigValid(string $scoreConfig);
}
