<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Model\Entity\PipelineConfig;
use App\Security\ACL\IPipelinePermissions;
use App\Model\Repository\Pipelines;
use App\Model\Entity\Pipeline;
use App\Helpers\ExerciseConfig\Validator as ConfigValidator;


/**
 * Endpoints for pipelines manipulation
 * @LoggedIn
 */

class PipelinesPresenter extends BasePresenter {

  /**
   * @var IPipelinePermissions
   * @inject
   */
  public $pipelineAcl;

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
   * Get a list of default boxes which might be used in pipeline.
   * @GET
   * @throws ForbiddenRequestException
   */
  public function actionGetDefaultBoxes() {
    if (!$this->pipelineAcl->canViewAll()) {
      throw new ForbiddenRequestException("You cannot list default boxes.");
    }

    $boxes = $this->boxService->getAllBoxes();
    $this->sendSuccessResponse($boxes);
  }

  /**
   * Get a list of pipelines with an optional filter
   * @GET
   * @param string $search text which will be searched in pipeline names
   * @throws ForbiddenRequestException
   */
  public function actionGetPipelines(string $search = null) {
    if (!$this->pipelineAcl->canViewAll()) {
      throw new ForbiddenRequestException("You cannot list all pipelines.");
    }

    $pipelines = $this->pipelines->searchByName($search);
    $pipelines = array_filter($pipelines, function (Pipeline $pipeline) {
      return $this->pipelineAcl->canViewDetail($pipeline);
    });
    $this->sendSuccessResponse($pipelines);
  }

  /**
   * Create pipeline.
   * @POST
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionCreatePipeline() {
    if (!$this->pipelineAcl->canCreate()) {
      throw new ForbiddenRequestException("You are not allowed to create pipeline.");
    }

    // create pipeline entity, persist it and return it
    $pipeline = Pipeline::create($this->getCurrentUser());
    $pipeline->setName("Pipeline by {$this->getCurrentUser()->getName()}");
    $this->pipelines->persist($pipeline);

    $this->sendSuccessResponse($pipeline);
  }

  /**
   * Delete an pipeline
   * @DELETE
   * @param string $id
   * @throws ForbiddenRequestException
   */
  public function actionRemovePipeline(string $id) {
    /** @var Pipeline $pipeline */
    $pipeline = $this->pipelines->findOrThrow($id);
    if (!$this->pipelineAcl->canRemove($pipeline)) {
      throw new ForbiddenRequestException("You are not allowed to remove this pipeline.");
    }

    $this->pipelines->remove($pipeline);
    $this->sendSuccessResponse("OK");
  }

  /**
   * Get pipeline based on given identification.
   * @GET
   * @param string $id Identifier of the pipeline
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionGetPipeline(string $id) {
    /** @var Pipeline $pipeline */
    $pipeline = $this->pipelines->findOrThrow($id);
    if (!$this->pipelineAcl->canViewDetail($pipeline)) {
      throw new ForbiddenRequestException("You are not allowed to get this pipeline.");
    }

    $this->sendSuccessResponse($pipeline);
  }

  /**
   * Update pipeline with given data.
   * @POST
   * @param string $id Identifier of the pipeline
   * @Param(type="post", name="name", description="Name of the pipeline")
   * @Param(type="post", name="version", description="Version of the edited pipeline")
   * @Param(type="post", name="description", description="Human readable description of pipeline")
   * @Param(type="post", name="pipeline", description="Pipeline configuration")
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws BadRequestException
   */
  public function actionUpdatePipeline(string $id) {
    /** @var Pipeline $pipeline */
    $pipeline = $this->pipelines->findOrThrow($id);
    if (!$this->pipelineAcl->canUpdate($pipeline)) {
      throw new ForbiddenRequestException("You are not allowed to update this pipeline.");
    }

    $req = $this->getRequest();
    $version = intval($req->getPost("version"));
    if ($version !== $pipeline->getVersion()) {
      throw new BadRequestException("The pipeline was edited in the meantime and the version has changed. Current version is {$pipeline->getVersion()}.");
    }

    // update fields of the pipeline
    $name = $req->getPost("name");
    $description = $req->getPost("description");
    $pipeline->setName($name);
    $pipeline->setDescription($description);
    $pipeline->setUpdatedAt(new \DateTime);
    $pipeline->incrementVersion();

    // get new configuration from parameters, parse it and check for format errors
    $pipelinePost = $req->getPost("pipeline");
    $pipelineArr = !empty($pipelinePost) ? $pipelinePost : array();
    $pipelineConfig = $this->exerciseConfigLoader->loadPipeline($pipelineArr);
    $oldConfig = $pipeline->getPipelineConfig();

    // validate new pipeline configuration
    $this->configValidator->validatePipeline($pipelineConfig);

    // create new pipeline configuration based on given data and store it in pipeline entity
    $newConfig = new PipelineConfig((string) $pipelineConfig, $this->getCurrentUser(), $oldConfig);
    $pipeline->setPipelineConfig($newConfig);
    $this->pipelines->flush();

    $this->sendSuccessResponse($pipeline);
  }

  /**
   * Check if the version of the pipeline is up-to-date.
   * @POST
   * @Param(type="post", name="version", validation="numericint", description="Version of the pipeline.")
   * @param string $id Identifier of the pipeline
   * @throws ForbiddenRequestException
   */
  public function actionValidatePipeline(string $id) {
    $pipeline = $this->pipelines->findOrThrow($id);

    if (!$this->pipelineAcl->canUpdate($pipeline)) {
      throw new ForbiddenRequestException("You cannot modify this pipeline.");
    }

    $req = $this->getRequest();
    $version = intval($req->getPost("version"));

    $this->sendSuccessResponse([
      "versionIsUpToDate" => $pipeline->getVersion() === $version
    ]);
  }

}
