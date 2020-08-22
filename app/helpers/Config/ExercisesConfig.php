<?php

namespace App\Helpers;

use Nette;
use Nette\Utils\Arrays;

class ExercisesConfig
{
    use Nette\SmartObject;

    // Restrictions
    private $testCountLimit;
    private $supplementaryFileCountLimit;
    private $supplementaryFileSizeLimit;

    // Defaults
    private $solutionFilesLimitDefault;
    private $solutionSizeLimitDefault;


    public function __construct(array $config)
    {
        $this->testCountLimit = Arrays::get($config, "testCountLimit", 100);
        $this->supplementaryFileCountLimit = Arrays::get($config, "supplementaryFileCountLimit", 200);
        $this->supplementaryFileSizeLimit = Arrays::get($config, "supplementaryFileSizeLimit", 256 * 1024 * 1024);
        $this->solutionFilesLimitDefault = Arrays::get($config, "solutionFilesLimitDefault", 10);
        $this->solutionSizeLimitDefault = Arrays::get($config, "solutionSizeLimitDefault", 256 * 1024);
    }

    public function getTestCountLimit(): int
    {
        return $this->testCountLimit;
    }

    public function getSupplementaryFileCountLimit(): int
    {
        return $this->supplementaryFileCountLimit;
    }

    public function getSupplementaryFileSizeLimit()
    {
        return $this->supplementaryFileSizeLimit;
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
