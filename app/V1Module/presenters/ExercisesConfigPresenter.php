<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ApiException;
use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidApiArgumentException;
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
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionGetEnvironmentConfigs(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Change runtime configuration of exercise.
     * Configurations can be added or deleted here, based on what is provided in arguments.
     * @POST
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     * @throws ExerciseConfigException
     * @throws NotFoundException
     */
    #[Post("environmentConfigs", new VArray(), "Environment configurations for the exercise")]
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionUpdateEnvironmentConfigs(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Get a basic exercise high level configuration.
     * @GET
     * @throws NotFoundException
     * @throws ExerciseConfigException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionGetConfiguration(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Set basic exercise configuration
     * @POST
     * @throws ExerciseConfigException
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ApiException
     * @throws ParseException
     */
    #[Post("config", new VArray(), "A list of basic high level exercise configuration")]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionSetConfiguration(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get variables for exercise configuration which are derived from given
     * pipelines and runtime environment.
     * @POST
     * @throws NotFoundException
     * @throws ExerciseConfigException
     */
    #[Post("runtimeEnvironmentId", new VString(1), "Environment identifier", required: false)]
    #[Post("pipelinesIds", new VArray(), "Identifiers of selected pipelines for one test")]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionGetVariablesForExerciseConfig(string $id)
    {
        // get request data
        $req = $this->getRequest();
        $this->sendSuccessResponse("OK");
    }

    /**
     * Get a description of resource limits for an exercise for given hwgroup.
     * @DEPRECATED
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ExerciseConfigException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("runtimeEnvironmentId", new VString(), required: true)]
    #[Path("hwGroupId", new VString(), required: true)]
    public function actionGetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Set resource limits for an exercise for given hwgroup.
     * @DEPRECATED
     * @POST
     * @throws ApiException
     * @throws ExerciseConfigException
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ParseException
     * @throws ExerciseCompilationException
     */
    #[Post("limits", new VArray(), "A list of resource limits for the given environment and hardware group")]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("runtimeEnvironmentId", new VString(), required: true)]
    #[Path("hwGroupId", new VString(), required: true)]
    public function actionSetHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Remove resource limits of given hwgroup from an exercise.
     * @DEPRECATED
     * @DELETE
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("runtimeEnvironmentId", new VString(), required: true)]
    #[Path("hwGroupId", new VString(), required: true)]
    public function actionRemoveHardwareGroupLimits(string $id, string $runtimeEnvironmentId, string $hwGroupId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get a description of resource limits for given exercise (all hwgroups all environments).
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionGetLimits(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Update resource limits for given exercise.
     * If limits for particular hwGroup or environment are not posted, no change occurs.
     * If limits for particular hwGroup or environment are posted as null, they are removed.
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ExerciseConfigException
     */
    #[Post("limits", new VArray(), "A list of resource limits in the same format as getLimits endpoint yields.")]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionSetLimits(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Get score configuration for exercise based on given identification.
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionGetScoreConfig(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Set score configuration for exercise.
     * @POST
     * @throws ExerciseConfigException
     */
    #[Post("scoreCalculator", new VString(), "ID of the score calculator")]
    #[Post(
        "scoreConfig",
        new VMixed(),
        "A configuration of the score calculator (the format depends on the calculator type)",
        nullable: true,
    )]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionSetScoreConfig(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get tests for exercise based on given identification.
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionGetTests(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Set tests for exercise based on given identification.
     * @POST
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     * @throws ExerciseConfigException
     */
    #[Post("tests", new VArray(), "An array of tests which will belong to exercise")]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionSetTests(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
