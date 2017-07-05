<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Loader;
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

    // @todo

    $this->sendSuccessResponse();
  }

}
