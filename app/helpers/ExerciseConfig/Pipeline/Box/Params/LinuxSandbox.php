<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box\Params;

/**
 * Linux sandbox identification for compilation purposes.
 */
class LinuxSandbox
{
    public const DEFAULT = ""; // use the sandbox specified in the worker configuration

    // these are currently not used as we rely on worker configuration, but that may change in the future
    public const ISOLATE = "isolate";
    public const GUARDIAN = "recodex-guardian";
}
