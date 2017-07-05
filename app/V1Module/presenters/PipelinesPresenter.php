<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Loader;
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
