<?php

namespace App\Model\Repository;
use App\Model\Entity\UploadedFile;
use Kdyby\Doctrine\EntityManager;

use App\Exceptions\NotFoundException;
use App\Model\Entity\RuntimeConfig;
use App\Model\Entity\Assignment;

class RuntimeConfigs extends BaseRepository {

  /**
   * @var RuntimeEnvironments
   */
  private $runtimeEnvironments;

  public function __construct(EntityManager $em, RuntimeEnvironments $environments) {
    parent::__construct($em, RuntimeConfig::class);
    $this->runtimeEnvironments = $environments;
  }

  /**
   * Detect the configuration of the runtime environment for a given assignment
   * by the extensions of submitted files.
   * @param Assignment     $assignment   The assignment
   * @param UploadedFile[]  $files        The files
   * @return RuntimeConfig
   * @throws NotFoundException
   */
  public function detectOrThrow(Assignment $assignment, array $files): RuntimeConfig {
    $runtimeEnvironment = $this->runtimeEnvironments->detectOrThrow($files);
    $configs = $assignment->getRuntimeConfigs()->filter(function (RuntimeConfig $config) use ($runtimeEnvironment) {
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
