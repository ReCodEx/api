<?php

namespace App\Helpers\JobConfig;

use App\Helpers\ExerciseConfig\Compiler;
use App\Model\Entity\User;

/**
 * @todo
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

  public function __construct(Storage $storage, Compiler $compiler) {
    $this->storage = $storage;
    $this->compiler = $compiler;
  }

  /**
   * Generate job configuration from exercise configuration and save it in the
   * job configuration storage.
   * @param User $user
   * @return array first item is path where job configuration is stored,
   * second list item is JobConfig itself
   */
  public function generateJobConfig(User $user): array {
    $jobConfig = $this->compiler->compile();
    $jobConfigPath = $this->storage->save($jobConfig, $user);
    return array($jobConfigPath, $jobConfig);
  }
}
