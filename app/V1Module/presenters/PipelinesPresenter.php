<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\FileStorageManager;
use App\Model\Entity\PipelineConfig;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\SupplementaryExerciseFiles;
use App\Model\Repository\Exercises;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\View\PipelineViewFactory;
use App\Model\View\ExerciseViewFactory;
use App\Security\ACL\IExercisePermissions;
use App\Security\ACL\IPipelinePermissions;
use App\Model\Repository\Pipelines;
use App\Model\Entity\Pipeline;
use App\Helpers\ExerciseConfig\Validator as ConfigValidator;
use Exception;

/**
 * Endpoints for pipelines manipulation
 * @LoggedIn
 */
class PipelinesPresenter extends BasePresenter
{
    /**
     * @var IPipelinePermissions
     * @inject
     */
    public $pipelineAcl;

    /**
     * @var IExercisePermissions
     * @inject
     */
    public $exerciseAcl;

    /**
     * @var Pipelines
     * @inject
     */
    public $pipelines;

    /**
     * @var Exercises
     * @inject
     */
    public $exercises;

    /**
     * @var RuntimeEnvironments
     * @inject
     */
    public $runtimes;

    /**
     * @var Loader
     * @inject
     */
    public $exerciseConfigLoader;

    /**
     * @var BoxService
     * @inject
     */
    public $boxService;

    /**
     * @var ConfigValidator
     * @inject
     */
    public $configValidator;

    /**
     * @var UploadedFiles
     * @inject
     */
    public $uploadedFiles;

    /**
     * @var SupplementaryExerciseFiles
     * @inject
     */
    public $supplementaryFiles;

    /**
     * @var PipelineViewFactory
     * @inject
     */
    public $pipelineViewFactory;

    /**
     * @var ExerciseViewFactory
     * @inject
     */
    public $exerciseViewFactory;

    /**
     * @var FileStorageManager
     * @inject
     */
    public $fileStorage;


    public function noncheckGetDefaultBoxes()
    {
        if (!$this->pipelineAcl->canViewAll()) {
            throw new ForbiddenRequestException("You cannot list default boxes.");
        }
    }

    /**
     * Get a list of default boxes which might be used in pipeline.
     * @GET
     */
    public function actionGetDefaultBoxes()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDefault(string $search = null)
    {
        if (!$this->pipelineAcl->canViewAll()) {
            throw new ForbiddenRequestException("You cannot list all pipelines.");
        }
    }

    /**
     * Get a list of pipelines with an optional filter, ordering, and pagination pruning.
     * The result conforms to pagination protocol.
     * @GET
     * @param int $offset Index of the first result.
     * @param int|null $limit Maximal number of results returned.
     * @param string|null $orderBy Name of the column (column concept). The '!' prefix indicate descending order.
     * @param array|null $filters Named filters that prune the result.
     * @param string|null $locale Currently set locale (used to augment order by clause if necessary),
     */
    public function actionDefault(
        int $offset = 0,
        int $limit = null,
        string $orderBy = null,
        array $filters = null,
        string $locale = null
    ) {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Create a brand new pipeline.
     * @POST
     * @Param(type="post", name="global", validation="bool", required=false,
     *        description="Whether the pipeline is global (has no author, is used in generic runtimes)")
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionCreatePipeline()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Create a complete copy of given pipeline.
     * @POST
     * @param string $id identification of pipeline to be copied
     * @Param(type="post", name="global", validation="bool", required=false,
     *        description="Whether the pipeline is global (has no author, is used in generic runtimes)")
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionForkPipeline(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemovePipeline(string $id)
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelines->findOrThrow($id);
        if (!$this->pipelineAcl->canRemove($pipeline)) {
            throw new ForbiddenRequestException("You are not allowed to remove this pipeline.");
        }
    }

    /**
     * Delete an pipeline
     * @DELETE
     * @param string $id
     * @throws NotFoundException
     */
    public function actionRemovePipeline(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetPipeline(string $id)
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelines->findOrThrow($id);
        if (!$this->pipelineAcl->canViewDetail($pipeline)) {
            throw new ForbiddenRequestException("You are not allowed to get this pipeline.");
        }
    }

    /**
     * Get pipeline based on given identification.
     * @GET
     * @param string $id Identifier of the pipeline
     * @throws NotFoundException
     */
    public function actionGetPipeline(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdatePipeline(string $id)
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelines->findOrThrow($id);
        if (!$this->pipelineAcl->canUpdate($pipeline)) {
            throw new ForbiddenRequestException("You are not allowed to update this pipeline.");
        }
    }

    /**
     * Update pipeline with given data.
     * @POST
     * @param string $id Identifier of the pipeline
     * @Param(type="post", name="name", validation="string:2..", description="Name of the pipeline")
     * @Param(type="post", name="version", validation="numericint", description="Version of the edited pipeline")
     * @Param(type="post", name="description", description="Human readable description of pipeline")
     * @Param(type="post", name="pipeline", description="Pipeline configuration", required=false)
     * @Param(type="post", name="parameters", validation="array", description="A set of parameters", required=false)
     * @Param(type="post", name="global", validation="bool", required=false,
     *        description="Whether the pipeline is global (has no author, is used in generic runtimes)")
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws ExerciseConfigException
     * @throws InvalidArgumentException
     */
    public function actionUpdatePipeline(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateRuntimeEnvironments(string $id)
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelines->findOrThrow($id);
        if (!$this->pipelineAcl->canUpdateEnvironments($pipeline)) {
            throw new ForbiddenRequestException(
                "You are not allowed to update associations between a pipeline and runtime environments."
            );
        }
    }

    /**
     * Set runtime environments associated with given pipeline.
     * @param string $id Identifier of the pipeline
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionUpdateRuntimeEnvironments(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Check if the version of the pipeline is up-to-date.
     * @POST
     * @Param(type="post", name="version", validation="numericint", description="Version of the pipeline.")
     * @param string $id Identifier of the pipeline
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionValidatePipeline(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUploadSupplementaryFiles(string $id)
    {
        $pipeline = $this->pipelines->findOrThrow($id);
        if (!$this->pipelineAcl->canUpdate($pipeline)) {
            throw new ForbiddenRequestException("You cannot update this pipeline.");
        }
    }

    /**
     * Associate supplementary files with a pipeline and upload them to remote file server
     * @POST
     * @Param(type="post", name="files", description="Identifiers of supplementary files")
     * @param string $id identification of pipeline
     * @throws ForbiddenRequestException
     * @throws SubmissionFailedException
     * @throws NotFoundException
     */
    public function actionUploadSupplementaryFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetSupplementaryFiles(string $id)
    {
        $pipeline = $this->pipelines->findOrThrow($id);
        if (!$this->pipelineAcl->canViewDetail($pipeline)) {
            throw new ForbiddenRequestException("You cannot view supplementary files for this pipeline.");
        }
    }

    /**
     * Get list of all supplementary files for a pipeline
     * @GET
     * @param string $id identification of pipeline
     * @throws NotFoundException
     */
    public function actionGetSupplementaryFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteSupplementaryFile(string $id, string $fileId)
    {
        $pipeline = $this->pipelines->findOrThrow($id);
        if (!$this->pipelineAcl->canUpdate($pipeline)) {
            throw new ForbiddenRequestException("You cannot delete supplementary files for this pipeline.");
        }
    }

    /**
     * Delete supplementary pipeline file with given id
     * @DELETE
     * @param string $id identification of pipeline
     * @param string $fileId identification of file
     * @throws NotFoundException
     */
    public function actionDeleteSupplementaryFile(string $id, string $fileId)
    {
        $this->sendSuccessResponse("OK");
    }


    public function noncheckGetPipelineExercises(string $id)
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelines->findOrThrow($id);
        if (!$this->pipelineAcl->canViewDetail($pipeline)) {
            throw new ForbiddenRequestException("You are not allowed to view the exercises of pipeline $id.");
        }
    }

    /**
     * Get all exercises that use given pipeline.
     * Only bare minimum is retrieved for each exercise (localized name and author).
     * @GET
     * @param string $id Identifier of the pipeline
     * @throws NotFoundException
     */
    public function actionGetPipelineExercises(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
