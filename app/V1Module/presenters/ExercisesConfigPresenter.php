<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ApiException;
use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ParseException;
use App\Helpers\ExerciseConfig\Helper;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Transformer;
use App\Helpers\ExerciseConfig\Updater;
use App\Helpers\ExerciseConfig\Validator;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\ExerciseConfig\ExerciseConfigChecker;
use App\Helpers\ExercisesConfig;
use App\Helpers\Evaluation\ScoreCalculatorAccessor;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseScoreConfig;
use App\Model\Entity\ExerciseLimits;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Entity\ExerciseTest;
use App\Model\Repository\Exercises;
use App\Model\Repository\ExerciseTests;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\Pipelines;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\Model\Repository\RuntimeEnvironments;
use App\Security\ACL\IExercisePermissions;
use Doctrine\Common\Collections\ArrayCollection;
use Nette\Utils\Arrays;

/**
 * Endpoints for exercise configuration manipulation
 * @LoggedIn
 */
class ExercisesConfigPresenter extends BasePresenter
{
    /**
     * @var Exercises
     * @inject
     */
    public $exercises;

    /**
     * @var ExerciseTests
     * @inject
     */
    public $exerciseTests;

    /**
     * @var Pipelines
     * @inject
     */
    public $pipelines;

    /**
     * @var Loader
     * @inject
     */
    public $exerciseConfigLoader;

    /**
     * @var Helper
     * @inject
     */
    public $exerciseConfigHelper;

    /**
     * @var Validator
     * @inject
     */
    public $configValidator;

    /**
     * @var Transformer
     * @inject
     */
    public $exerciseConfigTransformer;

    /**
     * @var Updater
     * @inject
     */
    public $exerciseConfigUpdater;

    /**
     * @var RuntimeEnvironments
     * @inject
     */
    public $runtimeEnvironments;

    /**
     * @var HardwareGroups
     * @inject
     */
    public $hardwareGroups;

    /**
     * @var ReferenceSolutionSubmissions
     * @inject
     */
    public $referenceSolutionEvaluations;

    /**
     * @var IExercisePermissions
     * @inject
     */
    public $exerciseAcl;

    /**
     * @var ScoreCalculatorAccessor
     * @inject
     */
    public $calculators;

    /**
     * @var ExercisesConfig
     * @inject
     */
    public $exerciseRestrictionsConfig;

    /**
     * @var ExerciseConfigChecker
     * @inject
     */
    public $configChecker;

    public function checkGetEnvironmentConfigs(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewConfig($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to get configuration of this exercise.");
        }
    }


    /**
     * Local micro-view factory, which constructs a response with environment configs of given exercise.
     */
    private function getEnvironmentConfigs(Exercise $exercise)
    {
        $configs = [];
        foreach ($exercise->getExerciseEnvironmentConfigs() as $runtimeConfig) {
            $configs[] = [
                "runtimeEnvironmentId" => $runtimeConfig->getRuntimeEnvironment()->getId(),
                "variablesTable" => $runtimeConfig->getParsedVariablesTable(),
            ];
        }
        return $configs;
    }


    /**
     * Get runtime configurations for exercise.
     * @GET
     * @param string $id Identifier of the exercise
     * @throws NotFoundException
     */
    public function actionGetEnvironmentConfigs(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        $configs = $this->getEnvironmentConfigs($exercise);
        $this->sendSuccessResponse($configs);
    }

    public function checkUpdateEnvironmentConfigs(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot update this exercise.");
        }
    }

    /**
     * Change runtime configuration of exercise.
     * Configurations can be added or deleted here, based on what is provided in arguments.
     * @POST
     * @param string $id identification of exercise
     * @Param(type="post", name="environmentConfigs", validation="array",
     *        description="Environment configurations for the exercise")
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws ExerciseConfigException
     * @throws NotFoundException
     */
    public function actionUpdateEnvironmentConfigs(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);

        // retrieve configuration and prepare some temp variables
        $environmentConfigs = $this->getRequest()->getPost("environmentConfigs");
        $configs = [];

        // configurations cannot be empty
        if (count($environmentConfigs) == 0) {
            throw new InvalidArgumentException("No entry for runtime configurations given.");
        }

        $runtimeEnvironments = new ArrayCollection();
        // go through given configurations and construct database entities
        foreach ($environmentConfigs as $environmentConfig) {
            $environmentId = $environmentConfig["runtimeEnvironmentId"];
            $environment = $this->runtimeEnvironments->findOrThrow($environmentId);

            // add runtime environment into resulting environments
            $runtimeEnvironments->add($environment);

            // check for duplicate environments
            if (array_key_exists($environmentId, $configs)) {
                throw new InvalidArgumentException("Duplicate entry for configuration '$environmentId''");
            }

            // load variables table for this runtime configuration
            $varTableArray = array_key_exists(
                "variablesTable",
                $environmentConfig
            ) ? $environmentConfig["variablesTable"] : array();
            $variablesTable = $this->exerciseConfigLoader->loadVariablesTable($varTableArray);

            // create all new runtime configuration
            $oldConfig = $exercise->getExerciseEnvironmentConfigByEnvironment($environment);
            $config = new ExerciseEnvironmentConfig(
                $environment,
                (string)$variablesTable,
                $this->getCurrentUser(),
                $oldConfig
            );

            // validation of newly create environment config
            $this->configValidator->validateEnvironmentConfig($exercise, $variablesTable);

            $configs[$environmentId] = !$config->equals($oldConfig) ? $config : $oldConfig;
        }

        // make changes and updates to database entity
        $exercise->updatedNow();
        $exercise->setRuntimeEnvironments($runtimeEnvironments);
        $this->exercises->replaceEnvironmentConfigs($exercise, $configs, false);
        $this->exerciseConfigUpdater->environmentsUpdated($exercise, $this->getCurrentUser(), false);

        // flush database changes and return successful response
        $this->exercises->flush();

        $this->configChecker->check($exercise);
        $this->exercises->flush();

        $configs = $this->getEnvironmentConfigs($exercise);
        $this->sendSuccessResponse($configs);
    }

    public function checkGetConfiguration(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewConfig($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to get configuration of this exercise.");
        }
    }

    /**
     * Get a basic exercise high level configuration.
     * @GET
     * @param string $id Identifier of the exercise
     * @throws NotFoundException
     * @throws ExerciseConfigException
     */
    public function actionGetConfiguration(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        $exerciseConfig = $exercise->getExerciseConfig();

        if ($exerciseConfig === null) {
            // should not be reached...
            throw new NotFoundException("Configuration for the exercise not exists");
        }

        $parsedConfig = $this->exerciseConfigLoader->loadExerciseConfig($exerciseConfig->getParsedConfig());

        // create configuration array which will be returned
        $config = $this->exerciseConfigTransformer->fromExerciseConfig($parsedConfig);
        $this->sendSuccessResponse($config);
    }

    public function checkSetConfiguration(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to get configuration of this exercise.");
        }
    }

    /**
     * Set basic exercise configuration
     * @POST
     * @Param(type="post", name="config", validation="array",
     *        description="A list of basic high level exercise configuration")
     * @param string $id Identifier of the exercise
     * @throws ExerciseConfigException
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ApiException
     * @throws ParseException
     */
    public function actionSetConfiguration(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $oldConfig = $exercise->getExerciseConfig();
        if ($oldConfig === null) {
            throw new NotFoundException("Configuration for the exercise not exists");
        }

        // get configuration from post request and transform it into internal structure
        $req = $this->getRequest();
        $config = $req->getPost("config");
        $exerciseConfig = $this->exerciseConfigTransformer->toExerciseConfig($config);

        // validate new exercise config
        $this->configValidator->validateExerciseConfig($exercise, $exerciseConfig);

        // new config was provided, so construct new database entity
        $newConfig = new ExerciseConfig((string)$exerciseConfig, $this->getCurrentUser(), $oldConfig);

        if (!$newConfig->equals($oldConfig)) {
            // set new exercise configuration into exercise and flush changes
            $exercise->updatedNow();
            $exercise->setExerciseConfig($newConfig);
            $this->exercises->flush();

            $this->configChecker->check($exercise);
            $this->exercises->flush();
        }

        $config = $this->exerciseConfigTransformer->fromExerciseConfig($exerciseConfig);
        $this->sendSuccessResponse($config);
    }

    public function checkGetVariablesForExerciseConfig(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewConfig($exercise)) {
            throw new ForbiddenRequestException(
                "You are not allowed to get variables for this exercise configuration."
            );
        }
    }

    /**
     * Get variables for exercise configuration which are derived from given
     * pipelines and runtime environment.
     * @POST
     * @param string $id Identifier of the exercise
     * @Param(type="post", name="runtimeEnvironmentId", validation="string:1..", required=false,
     *        description="Environment identifier")
     * @Param(type="post", name="pipelinesIds", validation="array",
     *        description="Identifiers of selected pipelines for one test")
     * @throws NotFoundException
     * @throws ExerciseConfigException
     */
    public function actionGetVariablesForExerciseConfig(string $id)
    {
        // get request data
        $req = $this->getRequest();
        $runtimeEnvironmentId = $req->getPost("runtimeEnvironmentId");
        $pipelinesIds = $req->getPost("pipelinesIds");

        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);

        // prepare environment configuration if needed
        if ($runtimeEnvironmentId !== null) {
            $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
            $environmentConfig = $exercise->getExerciseEnvironmentConfigByEnvironment($environment);
            $environmentVariables = $this->exerciseConfigLoader->loadVariablesTable(
                $environmentConfig->getParsedVariablesTable()
            );
        } else {
            $environmentVariables = new VariablesTable();
        }

        // compute result and send it back
        $result = $this->exerciseConfigHelper->getVariablesForExercise($pipelinesIds, $environmentVariables);
        $this->sendSuccessResponse($result);
    }

    public function checkGetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewLimits($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to get limits for this exercise.");
        }
    }

    /**
     * Get a description of resource limits for an exercise for given hwgroup.
     * @DEPRECATED
     * @GET
     * @param string $id Identifier of the exercise
     * @param string $runtimeEnvironmentId
     * @param string $hwGroupId
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ExerciseConfigException
     */
    public function actionGetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);

        $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
        $hwGroup = $this->hardwareGroups->findOrThrow($hwGroupId);

        // check if exercise defines requested environment
        if (!$exercise->getRuntimeEnvironments()->contains($environment)) {
            throw new NotFoundException("Specified environment '$runtimeEnvironmentId' not defined for this exercise");
        }

        $limits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
        if ($limits === null) {
            // there are no specified limits for this combination of environments
            // and hwgroup yet, so return empty array
            $limits = array();
        } else {
            $limits = $limits->getParsedLimits();
        }

        $this->sendSuccessResponse($limits);
    }

    public function checkSetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canSetLimits($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to set limits for this exercise.");
        }
    }

    /**
     * Set resource limits for an exercise for given hwgroup.
     * @DEPRECATED
     * @POST
     * @Param(type="post", name="limits", validation="array",
     *        description="A list of resource limits for the given environment and hardware group")
     * @param string $id Identifier of the exercise
     * @param string $runtimeEnvironmentId
     * @param string $hwGroupId
     * @throws ApiException
     * @throws ExerciseConfigException
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ParseException
     * @throws ExerciseCompilationException
     */
    public function actionSetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);

        $limits = $this->getRequest()->getPost("limits");
        $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
        $hwGroup = $this->hardwareGroups->findOrThrow($hwGroupId);

        // check if exercise defines requested environment
        if (!$exercise->getRuntimeEnvironments()->contains($environment)) {
            throw new NotFoundException("Specified environment '$runtimeEnvironmentId' not defined for this exercise");
        }

        // using loader load limits into internal structure which should detect formatting errors
        $exerciseLimits = $this->exerciseConfigLoader->loadExerciseLimits($limits);
        // validate new limits
        $this->configValidator->validateExerciseLimits($exercise, $hwGroup->getMetadata(), $exerciseLimits);

        // new limits were provided, so construct new database entity
        $oldLimits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
        $newLimits = new ExerciseLimits(
            $environment,
            $hwGroup,
            (string)$exerciseLimits,
            $this->getCurrentUser(),
            $oldLimits
        );

        // remove old limits for corresponding environment and hwgroup and add new ones
        // also do not forget to set hwgroup to exercise
        $exercise->removeExerciseLimits($oldLimits); // if there were ones before
        $exercise->addExerciseLimits($newLimits);
        $exercise->removeHardwareGroup($hwGroup); // if there was one before
        $exercise->addHardwareGroup($hwGroup);

        // update and return
        $exercise->updatedNow();
        $this->exercises->flush();

        // check exercise configuration
        $this->configChecker->check($exercise);
        $this->exercises->flush();
        $this->sendSuccessResponse($newLimits->getParsedLimits());
    }

    public function checkRemoveHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canSetLimits($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to set limits for this exercise.");
        }
    }

    /**
     * Remove resource limits of given hwgroup from an exercise.
     * @DEPRECATED
     * @DELETE
     * @param string $id Identifier of the exercise
     * @param string $runtimeEnvironmentId
     * @param string $hwGroupId
     * @throws NotFoundException
     */
    public function actionRemoveHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        $environment = $this->runtimeEnvironments->findOrThrow($runtimeEnvironmentId);
        $hwGroup = $this->hardwareGroups->findOrThrow($hwGroupId);

        // find requested limits
        $limits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
        if (!$limits) {
            throw new NotFoundException("Specified limits not found");
        }

        // make changes persistent
        $exercise->removeExerciseLimits($limits);
        $exercise->removeHardwareGroup($hwGroup);

        $exercise->updatedNow();
        $this->exercises->flush();

        // check exercise configuration
        $this->configChecker->check($exercise);
        $this->exercises->flush();
        $this->sendSuccessResponse("OK");
    }

    public function checkGetLimits(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewLimits($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to get limits for this exercise.");
        }
    }

    /**
     * Get a description of resource limits for given exercise (all hwgroups all environments).
     * @GET
     * @param string $id Identifier of the exercise
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionGetLimits(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);

        // prepare the result structure -- array [hwGroup][envId][testId] -> limits
        $limits = [];
        foreach ($exercise->getHardwareGroups() as $hwGroup) {
            $limits[$hwGroup->getId()] = [];
            foreach ($exercise->getRuntimeEnvironments() as $environment) {
                $limits[$hwGroup->getId()][$environment->getId()] = null;
            }
        }

        // fill existing limits into result structure
        foreach ($exercise->getExerciseLimits() as $limit) {
            $limits[$limit->getHardwareGroup()->getId()][$limit->getRuntimeEnvironment()->getId(
            )] = $limit->getParsedLimits();
        }
        $this->sendSuccessResponse($limits);
    }

    public function checkSetLimits(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canSetLimits($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to set limits for this exercise.");
        }
    }

    /**
     * Update resource limits for given exercise.
     * If limits for particular hwGroup or environment are not posted, no change occurs.
     * If limits for particular hwGroup or environment are posted as null, they are removed.
     * @POST
     * @Param(type="post", name="limits", validation="array",
     *        description="A list of resource limits in the same format as getLimits endpoint yields.")
     * @param string $id Identifier of the exercise
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ExerciseConfigException
     */
    public function actionSetLimits(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);

        $limits = $this->getRequest()->getPost("limits");
        foreach ($limits as $hwGroupId => $hwGroupLimits) {
            $hwGroup = $this->hardwareGroups->findOrThrow($hwGroupId);
            if (!$exercise->getHardwareGroups()->contains($hwGroup)) {
                throw new NotFoundException("Specified hardware group '$hwGroupId' not defined for this exercise");
            }

            foreach ($hwGroupLimits as $envId => $envLimits) {
                $environment = $this->runtimeEnvironments->findOrThrow($envId);
                if (!$exercise->getRuntimeEnvironments()->contains($environment)) {
                    throw new NotFoundException("Specified environment '$envId' not defined for this exercise");
                }

                $oldLimits = $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup);
                $exercise->removeExerciseLimits($oldLimits); // if there were ones before

                if ($envLimits) {
                    // load and validate a structured limits object
                    $envLimitsObj = $this->exerciseConfigLoader->loadExerciseLimits($envLimits);
                    $this->configValidator->validateExerciseLimits($exercise, $hwGroup->getMetadata(), $envLimitsObj);

                    // create and add new limits
                    $newLimits = new ExerciseLimits(
                        $environment,
                        $hwGroup,
                        (string)$envLimitsObj,
                        $this->getCurrentUser(),
                        $oldLimits
                    );
                    $exercise->addExerciseLimits($newLimits);
                }
            }
        }

        // final updates and checks...
        $exercise->updatedNow();
        $this->configChecker->check($exercise);
        $this->exercises->flush();
        $this->forward('ExercisesConfig:getLimits', $id);
    }

    public function checkGetScoreConfig(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewScoreConfig($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to view score config for this exercise.");
        }
    }

    /**
     * Get score configuration for exercise based on given identification.
     * @GET
     * @param string $id Identifier of the exercise
     */
    public function actionGetScoreConfig(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        // yes... it is just easy as that
        $this->sendSuccessResponse($exercise->getScoreConfig());
    }

    public function checkSetScoreConfig(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canSetScoreConfig($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to set score config for this exercise.");
        }
    }

    /**
     * Set score configuration for exercise.
     * @POST
     * @Param(type="post", name="scoreCalculator", validation="string", description="ID of the score calculator")
     * @Param(type="post", name="scoreConfig",
     *        description="A configuration of the score calculator (the format depends on the calculator type)")
     * @param string $id Identifier of the exercise
     * @throws ExerciseConfigException
     */
    public function actionSetScoreConfig(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $oldConfig = $exercise->getScoreConfig();

        $req = $this->getRequest();
        $calculatorName = $req->getPost("scoreCalculator");
        $config = $req->getPost("scoreConfig");

        // validate score configuration
        $calculator = $this->calculators->getCalculator($calculatorName);
        $normalizedConfig = $calculator->validateAndNormalizeScore($config);  // throws if validation fails

        if ($calculatorName !== $oldConfig->getCalculator() || !$oldConfig->configEquals($config)) {
            $newConfig = new ExerciseScoreConfig($calculatorName, $config, $oldConfig);
            $exercise->updatedNow();
            $exercise->setScoreConfig($newConfig);
            $this->exercises->flush();

            // check exercise configuration
            $this->configChecker->check($exercise);
            $this->exercises->flush();
        }
        $this->sendSuccessResponse($exercise->getScoreConfig());
    }

    public function checkGetTests(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewDetail($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to view tests for this exercise.");
        }
    }

    /**
     * Get tests for exercise based on given identification.
     * @GET
     * @param string $id Identifier of the exercise
     */
    public function actionGetTests(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        // Get to da responsa!
        $this->sendSuccessResponse($exercise->getExerciseTests()->getValues());
    }

    public function checkSetTests(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to set tests for this exercise.");
        }
    }

    /**
     * Set tests for exercise based on given identification.
     * @POST
     * @param string $id Identifier of the exercise
     * @Param(type="post", name="tests", validation="array",
     *        description="An array of tests which will belong to exercise")
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws ExerciseConfigException
     */
    public function actionSetTests(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        $req = $this->getRequest();
        $tests = $req->getPost("tests");

        /*
         * We need to implement CoW on tests.
         * All modified tests has to be newly created (with new IDs) and these
         * new IDs has to be propagated into configuration and limits.
         * Therefore a replacement mapping of updated tests is kept.
         */

        $newTests = [];
        $namesToOldIds = [];  // new test name => old test ID
        $testsModified = false;

        foreach ($tests as $test) {
            // Perform checks on the test name...
            if (!array_key_exists("name", $test)) {
                throw new InvalidArgumentException("tests", "name item not found in particular test");
            }

            $name = trim($test["name"]);
            if (!preg_match('/^[-a-zA-Z0-9_()\[\].! ]+$/', $name)) {
                throw new InvalidArgumentException("tests", "test name contains illicit characters");
            }
            if (strlen($name) > 64) {
                throw new InvalidArgumentException("tests", "test name too long (exceeds 64 characters)");
            }
            if (array_key_exists($name, $newTests)) {
                throw new InvalidArgumentException("tests", "two tests with the same name '$name' were specified");
            }

            $id = Arrays::get($test, "id", null);
            $description = trim(Arrays::get($test, "description", ""));

            // Prepare a test entity that is to be inserted into the new list of tests...
            $testEntity = $id ? $exercise->getExerciseTestById($id) : null;
            if ($testEntity === null) {
                // new exercise test was requested to be created
                $testsModified = true;

                if ($exercise->getExerciseTestByName($name)) {
                    throw new InvalidArgumentException("tests", "given test name '$name' is already taken");
                }

                $testEntity = new ExerciseTest($name, $description, $this->getCurrentUser());
                $this->exerciseTests->persist($testEntity);
            } elseif ($testEntity->getName() !== $name || $testEntity->getDescription() !== $description) {
                // an update is needed => a copy is made and old ID mapping is kept
                $testsModified = true;
                $namesToOldIds[$name] = $id;
                $testEntity = new ExerciseTest($name, $description, $testEntity->getAuthor());
                $this->exerciseTests->persist($testEntity);
            }
            // otherwise, the $testEntity is unchanged

            $newTests[$name] = $testEntity;
        }

        if (!$testsModified && count($exercise->getExerciseTestsIds()) === count($newTests)) {
            // nothing has changed
            $this->sendSuccessResponse(array_values($newTests));
            return;
        }

        $testCountLimit = $this->exerciseRestrictionsConfig->getTestCountLimit();
        if (count($newTests) > $testCountLimit) {
            throw new InvalidArgumentException(
                "tests",
                "The number of tests exceeds the configured limit ($testCountLimit)"
            );
        }

        // first, we create the new tests as independent entities, to get their IDs
        $this->exerciseTests->flush();  // actually creates the entities
        $idMapping = [];  // old ID => new ID
        foreach ($newTests as $test) {
            $this->exerciseTests->refresh($test);
            if (array_key_exists($test->getName(), $namesToOldIds)) {
                $oldId = $namesToOldIds[$test->getName()];
                $idMapping[$oldId] = $test->getId();
            }
        }

        // clear old tests and set new ones
        $exercise->getExerciseTests()->clear();
        $exercise->setExerciseTests(new ArrayCollection($newTests));
        $exercise->updatedNow();

        // update exercise configuration and test in here
        $this->exerciseConfigUpdater->testsUpdated($exercise, $this->getCurrentUser(), $idMapping, false);
        $this->configChecker->check($exercise);

        $this->exercises->flush();
        $this->sendSuccessResponse(array_values($newTests));
    }
}
