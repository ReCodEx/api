<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Model\Entity\PipelineConfig;
use App\Security\ACL\IPipelinePermissions;
use App\Model\Repository\Pipelines;
use App\Model\Entity\Pipeline;

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
   * @Param(type="post", name="pipeline", description="Pipeline configuration")
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionUpdatePipeline(string $id) {
    /** @var Pipeline $pipeline */
    $pipeline = $this->pipelines->findOrThrow($id);
    if (!$this->pipelineAcl->canUpdate($pipeline)) {
      throw new ForbiddenRequestException("You are not allowed to update this pipeline.");
    }

    // update name of the pipeline
    $name = $this->getRequest()->getPost("name");
    $pipeline->setName($name);

    // get new configuration from parameters, parse it and check for format errors
    $pipelinePost = $this->getRequest()->getPost("pipeline");
    $pipelineConfig = $this->exerciseConfigLoader->loadPipeline($pipelinePost);
    $oldConfig = $pipeline->getPipelineConfig();

    // create new pipeline configuration based on given data and store it in pipeline entity
    $newConfig = new PipelineConfig((string) $pipelineConfig, $this->getCurrentUser(), $oldConfig);
    $pipeline->setPipelineConfig($newConfig);
    $this->pipelines->flush();

    $this->sendSuccessResponse($pipeline);
  }

}
