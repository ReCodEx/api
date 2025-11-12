<?php

namespace App\Helpers;

use Nette;
use Nette\Utils\Arrays;

class ExercisesConfig
{
    use Nette\SmartObject;

    // Restrictions
    private $testCountLimit;
    private $exerciseFileCountLimit;
    private $exerciseFileSizeLimit;

    // Defaults
    private $solutionFilesLimitDefault;
    private $solutionSizeLimitDefault;


    public function __construct(array $config)
    {
        $this->testCountLimit = Arrays::get($config, "testCountLimit", 100);
        $this->exerciseFileCountLimit = Arrays::get($config, "exerciseFileCountLimit", 200);
        $this->exerciseFileSizeLimit = Arrays::get($config, "exerciseFileSizeLimit", 256 * 1024 * 1024);
        $this->solutionFilesLimitDefault = Arrays::get($config, "solutionFilesLimitDefault", 10);
        $this->solutionSizeLimitDefault = Arrays::get($config, "solutionSizeLimitDefault", 256 * 1024);
    }

    public function getTestCountLimit(): int
    {
        return $this->testCountLimit;
    }

    public function getExerciseFileCountLimit(): int
    {
        return $this->exerciseFileCountLimit;
    }

    public function getExerciseFileSizeLimit()
    {
        return $this->exerciseFileSizeLimit;
    }

    public function getSolutionFilesLimitDefault(): ?int
    {
        return $this->solutionFilesLimitDefault;
    }

    public function getSolutionSizeLimitDefault(): ?int
    {
        return $this->solutionSizeLimitDefault;
    }
}
