<?php

namespace App\Helpers\JobConfig;

use App\Helpers\ExerciseConfig\Compiler;
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Entity\User;

/**
 * Wrapper around compiler of exercise configuration to job configuration
 * which handles storing of job configuration on persistent data storage.
 */
class Generator {

  /**
   * @var Storage
   */
  private $storage;

  /**
   * @var Compiler
   */
  private $compiler;


  /**
   * Generator constructor.
   * @param Storage $storage
   * @param Compiler $compiler
   */
  public function __construct(Storage $storage, Compiler $compiler) {
    $this->storage = $storage;
    $this->compiler = $compiler;
  }

  /**
   * Generate job configuration from exercise configuration and save it in the
   * job configuration storage.
   * @param User $user
   * @param Exercise|Assignment $exerciseAssignment
   * @param RuntimeEnvironment $runtimeEnvironment
   * @param string[] $submittedFiles
   * @return array first item is path where job configuration is stored,
   * second list item is JobConfig itself
   */
  public function generateJobConfig(User $user, $exerciseAssignment,
      RuntimeEnvironment $runtimeEnvironment, array $submittedFiles): array {
    $jobConfig = $this->compiler->compile($exerciseAssignment, $runtimeEnvironment, $submittedFiles);
    $jobConfigPath = $this->storage->save($jobConfig, $user);
    return array($jobConfigPath, $jobConfig);
  }
}
