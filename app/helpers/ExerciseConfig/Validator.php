<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\EntityMetadata\HwGroupMeta;
use App\Helpers\ExerciseConfig\Validation\EnvironmentConfigValidator;
use App\Helpers\ExerciseConfig\Validation\ExerciseConfigValidator;
use App\Helpers\ExerciseConfig\Validation\ExerciseLimitsValidator;
use App\Helpers\ExerciseConfig\Validation\PipelineValidator;
use App\Model\Entity\Exercise;
use App\Model\Entity\Pipeline as PipelineEntity;


/**
 * Validator which should be used for whole exercise configuration machinery.
 * Rather than for internal validation of structures themselves, this helper
 * is considered only for validation of cross-structures data and references.
 * In here identifications of pipelines can be included or variables and
 * proper types of two joined ports. Also proper environments and hwgroups
 * can be checked here.
 */
class Validator {

  /**
   * @var Loader
   */
  private $loader;

  /**
   * @var ExerciseConfigValidator
   */
  private $exerciseConfigValidator;

  /**
   * @var PipelineValidator
   */
  private $pipelineValidator;

  /**
   * @var ExerciseLimitsValidator
   */
  private $exerciseLimitsValidator;

  /**
   * @var EnvironmentConfigValidator
   */
  private $environmentConfigValidator;

  /**
   * Validator constructor.
   * @param Loader $loader
   * @param ExerciseConfigValidator $exerciseConfigValidator
   * @param PipelineValidator $pipelineValidator
   * @param ExerciseLimitsValidator $exerciseLimitsValidator
   * @param EnvironmentConfigValidator $environmentConfigValidator
   */
  public function __construct(Loader $loader, ExerciseConfigValidator $exerciseConfigValidator,
      PipelineValidator $pipelineValidator, ExerciseLimitsValidator $exerciseLimitsValidator,
      EnvironmentConfigValidator $environmentConfigValidator) {
    $this->loader = $loader;
    $this->exerciseConfigValidator = $exerciseConfigValidator;
    $this->pipelineValidator = $pipelineValidator;
    $this->exerciseLimitsValidator = $exerciseLimitsValidator;
    $this->environmentConfigValidator = $environmentConfigValidator;
  }


  /**
   * Validate pipeline, all ports either have to have value specified and
   * pointing to variables table or it has to be blank. Value in port is always
   * reference to variables table, actual textual values are not supported here.
   * Types of port and variable in table has to be checked against each other.
   * The relations between ports can be one-to-many from the perspective of
   * output ports. That means one output port can be directed to multiple input
   * ones. There is possibility to have variable which is only aimed to input
   * port, in that case this variables has to be present in variables table of
   * pipeline. Variables whose only reference is in output port are not
   * supported. Validation also checks remote files presence in pipeline
   * database entity.
   * @param PipelineEntity $pipeline
   * @param Pipeline $pipelineConfig
   * @throws ExerciseConfigException
   */
  public function validatePipeline(PipelineEntity $pipeline, Pipeline $pipelineConfig) {
    $this->pipelineValidator->validate($pipeline, $pipelineConfig);
  }

  /**
   * Validation of exercise environment configuration. Presence of exercise
   * files is checked in all remote-file variables.
   * @param Exercise $exercise
   * @param VariablesTable $table
   * @throws ExerciseConfigException
   */
  public function validateEnvironmentConfig(Exercise $exercise, VariablesTable $table) {
    $this->environmentConfigValidator->validate($exercise, $table);
  }

  /**
   * Validation of exercise configuration against environment configurations,
   * that means mainly runtime environment identification. Another checks are
   * made against pipeline, again identification of pipeline is checked,
   * but in here also variables and if pipeline requires them is checked.
   * Validation also checks remote files presence in exercise database entity.
   * Exercise tests are checked against the ones in exercise.
   * @param Exercise $exercise
   * @param ExerciseConfig $config
   * @throws ExerciseConfigException
   */
  public function validateExerciseConfig(Exercise $exercise, ExerciseConfig $config) {
    $this->exerciseConfigValidator->validate($config, $exercise);
  }

  /**
   * Validation of exercise limits, limits are defined for tests which comes
   * from exercise database entity and are checked if existing.
   * @param Exercise $exercise
   * @param HwGroupMeta $hwGroupMeta
   * @param ExerciseLimits $limits
   * @throws ExerciseConfigException
   */
  public function validateExerciseLimits(Exercise $exercise, HwGroupMeta $hwGroupMeta, ExerciseLimits $limits) {
    $this->exerciseLimitsValidator->validate($exercise, $hwGroupMeta, $limits);
  }

}
