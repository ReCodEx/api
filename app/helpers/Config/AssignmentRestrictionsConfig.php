<?php

namespace App\Helpers;

use Nette;
use Nette\Utils\Arrays;

class AssignmentRestrictionsConfig
{
    use Nette\SmartObject;

    private $submissionsCountMetaLimit;

    private $maxPointsLimit;

    public function __construct(array $config)
    {
        $this->submissionsCountMetaLimit = Arrays::get($config, "submissionsCountMetaLimit", 100);
        $this->maxPointsLimit = Arrays::get($config, "maxPointsLimit", 10000);
    }

    public function getSubmissionsCountMetaLimit(): int
    {
        return $this->submissionsCountMetaLimit;
    }

    public function getMaxPointsLimit(): int
    {
        return $this->maxPointsLimit;
    }
}
