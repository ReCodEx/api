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

    public function noncheckGetEnvironmentConfigs(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateEnvironmentConfigs(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetConfiguration(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetConfiguration(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetVariablesForExerciseConfig(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemoveHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetLimits(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetLimits(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetScoreConfig(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetScoreConfig(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetTests(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetTests(string $id)
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
        $this->sendSuccessResponse("OK");
    }
}
