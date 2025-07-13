<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\BadRequestException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidApiArgumentException;
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
     */
    #[Query("offset", new VInt(), "Index of the first result.", required: false)]
    #[Query("limit", new VInt(), "Maximal number of results returned.", required: false, nullable: true)]
    #[Query(
        "orderBy",
        new VString(),
        "Name of the column (column concept). The '!' prefix indicate descending order.",
        required: false,
        nullable: true,
    )]
    #[Query("filters", new VArray(), "Named filters that prune the result.", required: false, nullable: true)]
    #[Query(
        "locale",
        new VString(),
        "Currently set locale (used to augment order by clause if necessary),",
        required: false,
        nullable: true,
    )]
    public function actionDefault(
        int $offset = 0,
        ?int $limit = null,
        ?string $orderBy = null,
        ?array $filters = null,
        ?string $locale = null
    ) {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Create a brand new pipeline.
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Post(
        "global",
        new VBool(),
        "Whether the pipeline is global (has no author, is used in generic runtimes)",
        required: false,
    )]
    public function actionCreatePipeline()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Create a complete copy of given pipeline.
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Post(
        "global",
        new VBool(),
        "Whether the pipeline is global (has no author, is used in generic runtimes)",
        required: false,
    )]
    #[Path("id", new VUuid(), "identification of pipeline to be copied", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the pipeline", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the pipeline", required: true)]
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
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws ExerciseConfigException
     * @throws InvalidApiArgumentException
     */
    #[Post("name", new VString(2), "Name of the pipeline")]
    #[Post("version", new VInt(), "Version of the edited pipeline")]
    #[Post("description", new VMixed(), "Human readable description of pipeline", nullable: true)]
    #[Post("pipeline", new VMixed(), "Pipeline configuration", required: false, nullable: true)]
    #[Post("parameters", new VArray(), "A set of parameters", required: false)]
    #[Post(
        "global",
        new VBool(),
        "Whether the pipeline is global (has no author, is used in generic runtimes)",
        required: false,
    )]
    #[Path("id", new VUuid(), "Identifier of the pipeline", required: true)]
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
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the pipeline", required: true)]
    public function actionUpdateRuntimeEnvironments(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Check if the version of the pipeline is up-to-date.
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Post("version", new VInt(), "Version of the pipeline.")]
    #[Path("id", new VUuid(), "Identifier of the pipeline", required: true)]
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
     * @throws ForbiddenRequestException
     * @throws SubmissionFailedException
     * @throws NotFoundException
     */
    #[Post("files", new VMixed(), "Identifiers of supplementary files", nullable: true)]
    #[Path("id", new VUuid(), "identification of pipeline", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "identification of pipeline", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "identification of pipeline", required: true)]
    #[Path("fileId", new VString(), "identification of file", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the pipeline", required: true)]
    public function actionGetPipelineExercises(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
