<?php

namespace App\Helpers;

use Nette\Utils\Arrays;

/**
 * Class that stores configuration for SubmissionHelper.
 * This helper is separated from SubmissionHelper so it can be used independently (to prevent cyclic dependencies).
 */
class SubmissionConfigHelper
{
    /** @var bool */
    private $locked = false;

    /** @var string|string[] */
    private $lockedReason = "Unknown.";

    public function __construct(array $config)
    {
        $this->locked = Arrays::get($config, "locked", false);
        $this->lockedReason = Arrays::get($config, "lockedReason", "Unknown.");
    }

    /**
     * @return bool True if the backend is locked out in the configuration and submissions are not possible.
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * @return string|string[] Message with locked reason. Either a string or localized strings [ locale => message ].
     */
    public function getLockedReason(): mixed
    {
        return $this->lockedReason;
    }

    /**
     * Flip the configuration into locked state. This is for debugging purposes only.
     * @param string|string[] $reason
     */
    public function setLocked(mixed $reason): void
    {
        $this->locked = true;
        $this->lockedReason = $reason;
    }
}
