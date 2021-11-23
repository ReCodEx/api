<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\SubmissionFailedException;
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
     * @var FileStorageManager
     * @inject
     */
    public $fileStorage;


    public function checkGetDefaultBoxes()
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
        $boxes = $this->boxService->getAllBoxes();
        $this->sendSuccessResponse($boxes);
    }

    public function checkDefault(string $search = null)
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
        $pagination = $this->getPagination(
            $offset,
            $limit,
            $locale,
            $orderBy,
            ($filters === null) ? [] : $filters,
            ['search', 'exerciseId', 'authorId']
        );

        $pipelines = $this->pipelines->getPreparedForPagination($pagination);
        $pipelines = array_filter(
            $pipelines,
            function (Pipeline $pipeline) {
                return $this->pipelineAcl->canViewDetail($pipeline);
            }
        );
        $pipelines = $this->pipelineViewFactory->getPipelines($pipelines);
        $this->sendPaginationSuccessResponse($pipelines, $pagination, true); // true = needs to be sliced inside
    }

    /**
     * Create pipeline.
     * @POST
     * @Param(type="post", name="exerciseId", description="Exercise identification", required=false)
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionCreatePipeline()
    {
        $exercise = null;
        if ($this->getRequest()->getPost("exerciseId")) {
            $exercise = $this->exercises->findOrThrow($this->getRequest()->getPost("exerciseId"));
        }

        if (!$this->pipelineAcl->canCreate()) {
            throw new ForbiddenRequestException("You are not allowed to create pipeline.");
        }
        if ($exercise && !$this->exerciseAcl->canAttachPipeline($exercise)) {
            throw new ForbiddenRequestException(
                "You are not allowed to attach newly created pipeline to given exercise."
            );
        }

        // create pipeline entity, persist it and return it
        $pipeline = Pipeline::create($this->getCurrentUser(), $exercise);
        $pipeline->setName("Pipeline by {$this->getCurrentUser()->getName()}");
        $this->pipelines->persist($pipeline);
        $this->sendSuccessResponse($this->pipelineViewFactory->getPipeline($pipeline));
    }

    /**
     * Fork pipeline, if exercise identification is given pipeline is forked
     * to specified exercise.
     * @POST
     * @param string $id identification of pipeline
     * @Param(type="post", name="exerciseId", description="Exercise identification", required=false)
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionForkPipeline(string $id)
    {
        $req = $this->getRequest();
        $exerciseId = $req->getPost("exerciseId");
        $exercise = $exerciseId ? $this->exercises->findOrThrow($exerciseId) : null;
        $pipeline = $this->pipelines->findOrThrow($id);

        if (!$this->pipelineAcl->canFork($pipeline)) {
            throw new ForbiddenRequestException("You are not allowed to fork pipeline.");
        }
        if ($exercise && !$this->exerciseAcl->canAttachPipeline($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to attach forked pipeline to given exercise.");
        }

        // fork pipeline entity, persist it and return it
        $pipeline = Pipeline::forkFrom($this->getCurrentUser(), $pipeline, $exercise);
        $this->pipelines->persist($pipeline);
        $this->sendSuccessResponse($this->pipelineViewFactory->getPipeline($pipeline));
    }

    public function checkRemovePipeline(string $id)
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
        $pipeline = $this->pipelines->findOrThrow($id);
        $this->pipelines->remove($pipeline);
        $this->sendSuccessResponse("OK");
    }

    public function checkGetPipeline(string $id)
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
        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelines->findOrThrow($id);
        $this->sendSuccessResponse($this->pipelineViewFactory->getPipeline($pipeline));
    }

    public function checkUpdatePipeline(string $id)
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
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws ExerciseConfigException
     * @throws InvalidArgumentException
     */
    public function actionUpdatePipeline(string $id)
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelines->findOrThrow($id);

        $req = $this->getRequest();
        $version = intval($req->getPost("version"));
        if ($version !== $pipeline->getVersion()) {
            $v = $pipeline->getVersion();
            throw new BadRequestException(
                "The pipeline was edited in the meantime and the version has changed. Current version is $v."
            );
        }

        // update fields of the pipeline
        $name = $req->getPost("name");
        $description = $req->getPost("description");
        $pipeline->setName($name);
        $pipeline->setDescription($description);
        $pipeline->updatedNow();
        $pipeline->incrementVersion();

        // get new configuration from parameters, parse it and check for format errors
        $pipelinePost = $req->getPost("pipeline");
        if (!empty($pipelinePost)) {
            $pipelineConfig = $this->exerciseConfigLoader->loadPipeline($pipelinePost);
            $oldConfig = $pipeline->getPipelineConfig();

            // validate new pipeline configuration
            $this->configValidator->validatePipeline($pipeline, $pipelineConfig);

            // create new pipeline configuration based on given data and store it in pipeline entity
            $newConfig = new PipelineConfig((string)$pipelineConfig, $this->getCurrentUser(), $oldConfig);
            $pipeline->setPipelineConfig($newConfig);
            $this->pipelines->flush();
        }

        $parameters = $this->request->getPost("parameters") ?? [];
        $pipeline->setParameters($parameters);

        $this->sendSuccessResponse($this->pipelineViewFactory->getPipeline($pipeline));
    }

    public function checkUpdateRuntimeEnvironments(string $id)
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
        /** @var Pipeline $pipeline */
        $pipeline = $this->pipelines->findOrThrow($id);

        $envsIds = $this->getRequest()->getPost("environments");
        $envs = array_map(function ($envId) {
            return $this->runtimes->findOrThrow($envId);
        }, $envsIds);
        $pipeline->setRuntimeEnvironments($envs);

        $this->pipelines->flush();
        $this->sendSuccessResponse($this->pipelineViewFactory->getPipeline($pipeline));
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
        $pipeline = $this->pipelines->findOrThrow($id);

        if (!$this->pipelineAcl->canUpdate($pipeline)) {
            throw new ForbiddenRequestException("You cannot modify this pipeline.");
        }

        $req = $this->getRequest();
        $version = intval($req->getPost("version"));

        $this->sendSuccessResponse(
            [
                "versionIsUpToDate" => $pipeline->getVersion() === $version
            ]
        );
    }

    public function checkUploadSupplementaryFiles(string $id)
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
        $pipeline = $this->pipelines->findOrThrow($id);
        $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
        $supplementaryFiles = [];
        $currentSupplementaryFiles = [];

        /** @var SupplementaryExerciseFile $file */
        foreach ($pipeline->getSupplementaryEvaluationFiles() as $file) {
            $currentSupplementaryFiles[$file->getName()] = $file;
        }

        /** @var UploadedFile $file */
        foreach ($files as $file) {
            if (get_class($file) !== UploadedFile::class) {
                throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
            }

            if (array_key_exists($file->getName(), $currentSupplementaryFiles)) {
                /** @var SupplementaryExerciseFile $currentFile */
                $currentFile = $currentSupplementaryFiles[$file->getName()];
                $pipeline->getSupplementaryEvaluationFiles()->removeElement($currentFile);
            }

            $hash = $this->fileStorage->storeUploadedSupplementaryFile($file);
            $pipelineFile = SupplementaryExerciseFile::fromUploadedFileAndPipeline($file, $pipeline, $hash);
            $supplementaryFiles[] = $pipelineFile;

            $this->uploadedFiles->persist($pipelineFile, false);
            $this->uploadedFiles->remove($file, false);
        }

        $pipeline->updatedNow();
        $this->pipelines->flush();
        $this->uploadedFiles->flush();

        $this->sendSuccessResponse($supplementaryFiles);
    }

    public function checkGetSupplementaryFiles(string $id)
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
        $pipeline = $this->pipelines->findOrThrow($id);
        $this->sendSuccessResponse($pipeline->getSupplementaryEvaluationFiles()->getValues());
    }

    public function checkDeleteSupplementaryFile(string $id, string $fileId)
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
        $pipeline = $this->pipelines->findOrThrow($id);
        $file = $this->supplementaryFiles->findOrThrow($fileId);

        $pipeline->updatedNow();
        $pipeline->getSupplementaryEvaluationFiles()->removeElement($file);
        $this->pipelines->flush();

        $this->sendSuccessResponse("OK");
    }
}
