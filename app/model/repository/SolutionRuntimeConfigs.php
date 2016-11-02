<?php

namespace App\Model\Repository;
use Kdyby\Doctrine\EntityManager;

use App\Exceptions\NotFoundException;
use App\Model\Entity\SolutionRuntimeConfig;
use App\Model\Entity\Assignment;

class SolutionRuntimeConfigs extends BaseRepository {

  /**
   * @var RuntimeENvironments
   */
  private $runtimeEnvironments;

  public function __construct(EntityManager $em, RuntimeEnvironments $environments) {
    parent::__construct($em, SolutionRuntimeConfig::CLASS);
    $this->runtimeEnvironments = $environments;
  }

  /**
   * Detect the configuration of the runtime environment for a given assignment
   * by the extensions of submitted files.
   * @param Assignmenet     $assignment   The assignment
   * @param UploadedFile[]  $files        The files
   * @return SolutionRuntimeConfig
   * @throws NotFoundException
   */
  public function detectOrThrow(Assignment $assignment, array $files): SolutionRuntimeConfig {
    $runtimeEnvironment = $this->runtimeEnvironments->detectOrThrow($files);
    $configs = $assignment->getSolutionRuntimeConfigs()->filter(function ($config) use ($runtimeEnvironment) {
      return $config->getRuntimeEnvironment()->getId() === $runtimeEnvironment->getId();
    });

    if ($configs->count() === 0) {
      throw new NotFoundException("There is no suitable runtime configuration for the submitted files.");
    } else if ($configs->count() > 1) {
      throw new NotFoundException("There are multiple suitable runtime configurations for the submitted files - it is not possible to determine the correct one automatically.");
    }

    return $configs->first();
  }


}
