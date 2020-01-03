<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ApiException;
use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\ParseException;
use App\Helpers\EntityMetadata\Solution\SolutionParams;
use App\Helpers\EntityMetadata\Solution\SubmitVariable;
use App\Helpers\ExerciseConfig;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\Evaluation\ScoreCalculatorAccessor;
use App\Model\Entity\Exercise;
use App\Model\Entity\RuntimeEnvironment;
use Nette\SmartObject;
use Nette\Utils\Random;

/**
 * A helper that hopes to detect broken exercise configurations by attempting to compile them.
 */
class ExerciseConfigChecker
{
    use SmartObject;

    private $compiler;

    private $validator;

    private $loader;

    private $helper;

    /** @var ScoreCalculatorAccessor */
    public $calculators;

    public function __construct(
        ExerciseConfig\Compiler $compiler,
        ExerciseConfig\Validator $validator,
        ExerciseConfig\Loader $loader,
        ScoreCalculatorAccessor $calculators,
        ExerciseConfig\Helper $helper
    ) {
        $this->compiler = $compiler;
        $this->validator = $validator;
        $this->loader = $loader;
        $this->calculators = $calculators;
        $this->helper = $helper;
    }

    /**
     * Make up names of submitted files for a runtime environment.
     * This is necessary because we do not have any real submissions yet, but the compiler needs their names.
     * TODO when we implement a mechanism that ensures further constraints on submitted files, it must be reflected here
     * @param RuntimeEnvironment $environment
     * @return string[]
     * @throws ParseException
     */
    private function conjureSubmittedFiles(RuntimeEnvironment $environment): array
    {
        return array_map(
            function ($extension) {
                $random = Random::generate(20);
                return "recodex.{$random}.{$extension}";
            },
            $environment->getExtensionsList()
        );
    }

    /**
     * Make up names of submitted files for a runtime environment.
     * @param Exercise $exercise
     * @param RuntimeEnvironment $environment
     * @param string[] $submitFiles
     * @return SolutionParams
     * @throws ExerciseConfigException
     * @throws ParseException
     */
    private function conjureSubmitVariables(
        Exercise $exercise,
        RuntimeEnvironment $environment,
        array $submitFiles
    ): SolutionParams {
        $result = new SolutionParams();
        $envs = $this->helper->getSubmitVariablesForExercise($exercise);
        foreach ($envs as $envVars) {
            if ($envVars["runtimeEnvironmentId"] === $environment->getId()) {
                foreach ($envVars["variables"] as $variable) {
                    if (strpos($variable["type"], "string") === 0) {
                        $result->addVariable(
                            new SubmitVariable(["name" => $variable["name"], "value" => Random::generate()])
                        );
                    } else {
                        if (strpos($variable["type"], "file") === 0) {
                            $result->addVariable(
                                new SubmitVariable(["name" => $variable["name"], "value" => reset($submitFiles)])
                            );
                        } else {
                            // Uh-oh
                            throw new ExerciseConfigException(
                                "I should not generate this error. I really should not. Uh-oh... here we go"
                            );
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Validate limits.
     * @param Exercise $exercise
     * @return bool false if broken flag was set
     */
    private function validateLimits(Exercise $exercise): bool
    {
        foreach ($exercise->getRuntimeEnvironments() as $environment) {
            foreach ($exercise->getHardwareGroups() as $hardwareGroup) {
                $limitsEntity = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hardwareGroup);
                if ($limitsEntity === null) {
                    $exercise->setBroken(
                        sprintf(
                            "Limits for environment '%s' and hardware group '%s' not found",
                            $environment->getName(),
                            $hardwareGroup->getId()
                        )
                    );
                    return false;
                }

                $limits = null;

                try {
                    $limits = $this->loader->loadExerciseLimits($limitsEntity->getParsedLimits());
                } catch (ExerciseConfigException $exception) {
                    $exercise->setBroken(
                        sprintf(
                            "Loading limits from '%s' failed: %s",
                            $limitsEntity->getId(),
                            $exception->getMessage()
                        )
                    );
                    return false;
                }

                try {
                    $this->validator->validateExerciseLimits($exercise, $hardwareGroup->getMetadata(), $limits);
                } catch (ExerciseConfigException $exception) {
                    $exercise->setBroken(sprintf("Error in limit configuration: %s", $exception->getMessage()));
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate exercises environments configurations.
     * @param Exercise $exercise
     * @return bool false if broken flag was set
     * @throws ParseException
     */
    private function validateEnvironmentConfigurations(Exercise $exercise): bool
    {
        /** @var RuntimeEnvironment $environment */
        $environment = null;
        try {
            foreach ($exercise->getRuntimeEnvironments() as $environment) {
                $envConfig = $exercise->getExerciseEnvironmentConfigByEnvironment($environment);

                if ($envConfig === null) {
                    $exercise->setBroken(sprintf("Environment '%s' has no configuration", $environment->getId()));
                    return false;
                }

                $table = $this->loader->loadVariablesTable($envConfig->getParsedVariablesTable());
                $this->validator->validateEnvironmentConfig($exercise, $table);

                $submitFiles = $this->conjureSubmittedFiles($environment);
                $submitVariables = $this->conjureSubmitVariables($exercise, $environment, $submitFiles);

                $this->compiler->compile(
                    $exercise,
                    $environment,
                    CompilationParams::create($submitFiles, false, $submitVariables)
                );
            }
            $exercise->setNotBroken();
        } catch (ExerciseConfigException | ExerciseCompilationException $exception) {
            $exercise->setBroken(
                sprintf(
                    "Error in exercise configuration for environment '%s': %s",
                    $environment !== null ? $environment->getId() : "UNKNOWN",
                    $exception->getMessage()
                )
            );
            return false;
        }

        return true;
    }

    /**
     * Check the configuration of an exercise (including all environment configs) and set the `isBroken` flag if there is
     * an error.
     * @param Exercise $exercise the exercise whose configuration should be checked
     * @throws ParseException
     */
    public function check(Exercise $exercise)
    {
        if ($exercise->getRuntimeEnvironments()->count() === 0) {
            $exercise->setBroken("There are no runtime environments");
            return;
        }

        if ($exercise->getHardwareGroups()->count() === 0) {
            $exercise->setBroken("There are no hardware groups");
            return;
        }

        if ($exercise->getLocalizedTexts()->count() === 0) {
            $exercise->setBroken("There are no student descriptions");
            return;
        }

        if ($exercise->getExerciseConfig() === null) {
            $exercise->setBroken("There is no configuration for this exercise");
            return;
        }

        try {
            $config = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
            $this->validator->validateExerciseConfig($exercise, $config);
        } catch (ExerciseConfigException $exception) {
            $exercise->setBroken(sprintf("Global exercise configuration is invalid: %s", $exception->getMessage()));
            return;
        }

        // validate environments
        if (!$this->validateEnvironmentConfigurations($exercise)) {
            return;
        }

        // validate score configuration
        $calculator = $this->calculators->getCalculator($exercise->getScoreCalculator());
        if (!$calculator->isScoreConfigValid($exercise->getScoreConfig())) {
            $exercise->setBroken("The score configuration is invalid");
            return;
        }

        // validate limits
        if (!$this->validateLimits($exercise)) {
            return;
        }
    }
}
