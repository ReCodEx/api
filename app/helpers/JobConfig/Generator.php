<?php

namespace App\Helpers\JobConfig;

use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\JobConfigStorageException;
use App\Helpers\Evaluation\IExercise;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Compiler;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Entity\User;


/**
 * Holder structure for results from generation of job configuration.
 */
class GeneratorResult {

  /** @var string */
  private $jobConfigPath;
  /** @var JobConfig */
  private $jobConfig;

  public function __construct(string $jobConfigPath, JobConfig $jobConfig) {
    $this->jobConfigPath = $jobConfigPath;
    $this->jobConfig = $jobConfig;
  }

  /**
   * @return string
   */
  public function getJobConfigPath(): string {
    return $this->jobConfigPath;
  }

  /**
   * @return mixed
   */
  public function getJobConfig() {
    return $this->jobConfig;
  }

}

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
   * @param IExercise $exerciseAssignment
   * @param RuntimeEnvironment $runtimeEnvironment
   * @param CompilationParams $params
   * @return GeneratorResult
   * @throws ExerciseConfigException
   * @throws JobConfigStorageException
   * @throws ExerciseCompilationException
   */
  public function generateJobConfig(User $user, IExercise $exerciseAssignment,
      RuntimeEnvironment $runtimeEnvironment, CompilationParams $params): GeneratorResult {
    $jobConfig = $this->compiler->compile($exerciseAssignment, $runtimeEnvironment, $params);
    $jobConfigPath = $this->storage->save($jobConfig, $user);
    return new GeneratorResult($jobConfigPath, $jobConfig);
  }
}
