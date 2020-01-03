<?php

namespace App\Helpers\ExerciseConfig\Validation;

use App\Exceptions\ExerciseConfigException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Helper;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\PipelinesCache;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseEnvironmentConfig;

/**
 * Internal exercise configuration validation service.
 */
class ExerciseConfigValidator
{

    /**
     * @var Loader
     */
    private $loader;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * ExerciseConfigValidator constructor.
     * @param Loader $loader
     * @param Helper $helper
     */
    public function __construct(Loader $loader, Helper $helper)
    {
        $this->loader = $loader;
        $this->helper = $helper;
    }


    /**
     * @param ExerciseConfig $config
     * @param Exercise $exercise
     * @throws ExerciseConfigException
     */
    private function checkEnvironments(ExerciseConfig $config, Exercise $exercise)
    {
        $envSpecificConfigs = $exercise->getExerciseEnvironmentConfigs();

        if (count($config->getEnvironments()) !== count($envSpecificConfigs)) {
            throw new ExerciseConfigException(
                "The number of entries in environment-specific configuration differs from the number of allowed environments"
            );
        }

        /** @var string $environment */
        foreach ($config->getEnvironments() as $environment) {
            $matchingConfigExists = $envSpecificConfigs->exists(
                function ($key, ExerciseEnvironmentConfig $envConfig) use ($environment) {
                    return $envConfig->getRuntimeEnvironment()->getId() === $environment;
                }
            );

            if (!$matchingConfigExists) {
                throw new ExerciseConfigException(
                    "Environment $environment not found in environment-specific configuration"
                );
            }
        }
    }

    /**
     * @param ExerciseConfig $config
     * @param Exercise $exercise
     * @throws ExerciseConfigException
     */
    private function checkPipelines(ExerciseConfig $config, Exercise $exercise)
    {
        $exerciseTests = $exercise->getExerciseTestsIds();
        if (count($exerciseTests) !== count($config->getTests())) {
            throw new ExerciseConfigException(
                "Number of tests in configuration do not correspond to the ones in exercise"
            );
        }

        foreach ($config->getTests() as $testId => $test) {
            if (array_search($testId, $exerciseTests) === false) {
                throw new ExerciseConfigException("Test with id '{$testId}' not found in exercise tests");
            }

            // go through all environments in test
            foreach ($test->getEnvironments() as $envId => $environment) {
                // check pipelines in environment
                $environmentEntity = $exercise->getExerciseEnvironmentConfigs()->filter(
                    function (ExerciseEnvironmentConfig $envConfig) use ($envId) {
                        return $envConfig->getRuntimeEnvironment()->getId() === $envId;
                    }
                )->first();
                $environmentVariables = $this->loader->loadVariablesTable(
                    $environmentEntity->getParsedVariablesTable()
                );
                $this->checkPipelinesSection($exercise, $environment->getPipelines(), $environmentVariables, $envId);
            }
        }
    }

    /**
     * @param Exercise $exercise
     * @param array $pipelines
     * @param VariablesTable $environmentVariables
     * @param string $environment
     * @throws ExerciseConfigException
     */
    private function checkPipelinesSection(
        Exercise $exercise,
        array $pipelines,
        VariablesTable $environmentVariables,
        ?string $environment = null
    ) {
        $exerciseFiles = $exercise->getHashedSupplementaryFiles();

        // load pipeline configurations from database
        $pipelinesIds = [];
        foreach ($pipelines as $pipeline) {
            $pipelineId = $pipeline->getId();
            $pipelinesIds[] = $pipelineId;
        }

        // find expected variables for each pipeline
        $expectedVariables = $this->helper->getVariablesForExercise($pipelinesIds, $environmentVariables);

        /**
         * @var string $pipelineId
         * @var PipelineVars $pipelineVars
         */
        foreach ($pipelines as $i => $pipelineVars) {
            $pipelineId = $pipelineVars->getId();
            $expected = $expectedVariables[$i]["variables"];

            // get names of expected variables for this pipeline
            $expectedVariablesNames = array_map(
                function (Variable $variable) {
                    return $variable->getName();
                },
                $expected
            );
            $variables = $pipelineVars->getVariablesTable();

            foreach ($variables->getAll() as $variable) {
                if (!in_array($variable->getName(), $expectedVariablesNames)) {
                    throw new ExerciseConfigException(
                        sprintf(
                            "Variable '%s' is redundant in pipeline %s, environment %s",
                            $variable->getName(),
                            $pipelineId,
                            $environment ?? "default"
                        )
                    );
                }

                $expectedVariablesNames = array_filter(
                    $expectedVariablesNames,
                    function (string $name) use ($variable) {
                        return $name !== $variable->getName();
                    }
                );

                // check supplementary remote files if exists in exercise entity
                ValidationUtils::checkRemoteFilePresence($variable, $exerciseFiles, "exercise");
            }

            if (count($expectedVariablesNames) > 0) {
                throw new ExerciseConfigException(
                    sprintf(
                        "Missing values for variables: %s (pipeline %s, environment %s)",
                        implode(", ", $expectedVariablesNames),
                        $pipelineId,
                        $environment ?? "default"
                    )
                );
            }
        }
    }

    /**
     * Validate exercise configuration.
     * For more detailed description look at @ref App\Helpers\ExerciseConfig\Validator
     * @param ExerciseConfig $config
     * @param Exercise $exercise
     * @throws ExerciseConfigException
     */
    public function validate(ExerciseConfig $config, Exercise $exercise)
    {
        $this->checkEnvironments($config, $exercise);
        $this->checkPipelines($config, $exercise);
    }
}
