<?php

namespace App\Model\Helpers;

use Doctrine\Common\Collections\ArrayCollection;

interface IScoreCalculator {
    public function computeScore(string $scoreConfig, ArrayCollection $testResults);
    public function isScoreConfigValid(string $scoreConfig);
}
