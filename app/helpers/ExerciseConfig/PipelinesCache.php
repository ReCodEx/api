<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
use App\Exceptions\NotFoundException;
use App\Model\Repository\Pipelines;

/**
 * Cache for pipelines and parsed pipeline configurations.
 */
class PipelinesCache {

  /**
   * @var Loader
   */
  private $loader;

  /**
   * @var Pipelines
   */
  private $pipelines;

  /**
   * @var array
   */
  private $cache = [];

  /**
   * Constructor
   * @param Loader $loader
   * @param Pipelines $pipelines
   */
  public function __construct(Loader $loader, Pipelines $pipelines) {
    $this->loader = $loader;
    $this->pipelines = $pipelines;
  }


  /**
   * Load pipeline from database based on given identification.
   * @param string $id
   * @throws NotFoundException
   * @throws ExerciseConfigException
   */
  private function loadPipeline(string $id) {
    if (array_key_exists($id, $this->cache)) {
      return;
    }

    $pipelineEntity = $this->pipelines->findOrThrow($id);
    $pipelineConfig = $this->loader->loadPipeline($pipelineEntity->getPipelineConfig()->getParsedPipeline());
    $this->cache[$id] = [ $pipelineEntity, $pipelineConfig ];
  }

  /**
   * Get pipeline entity for given identification.
   * @param string $id identification of pipeline entity
   * @return \App\Model\Entity\Pipeline
   * @throws ExerciseConfigException
   * @throws NotFoundException
   */
  public function getPipeline(string $id): \App\Model\Entity\Pipeline {
    $this->loadPipeline($id);
    return $this->cache[$id][0];
  }

  /**
   * Get pipeline configuration structure for given pipeline identification.
   * @param string $id identification of pipeline entity
   * @return Pipeline
   * @throws ExerciseConfigException
   * @throws NotFoundException
   */
  public function getPipelineConfig(string $id): Pipeline {
    $this->loadPipeline($id);
    return $this->cache[$id][1];
  }
}
