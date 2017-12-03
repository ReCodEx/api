<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
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
   * Validator constructor.
   * @param Loader $loader
   * @param ExerciseConfigValidator $exerciseConfigValidator
   * @param PipelineValidator $pipelineValidator
   * @param ExerciseLimitsValidator $exerciseLimitsValidator
   */
  public function __construct(Loader $loader, ExerciseConfigValidator $exerciseConfigValidator,
      PipelineValidator $pipelineValidator, ExerciseLimitsValidator $exerciseLimitsValidator) {
    $this->loader = $loader;
    $this->exerciseConfigValidator = $exerciseConfigValidator;
    $this->pipelineValidator = $pipelineValidator;
    $this->exerciseLimitsValidator = $exerciseLimitsValidator;
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
   * Validation of exercise configuration against environment configurations,
   * that means mainly runtime environment identification. Another checks are
   * made against pipeline, again identification of pipeline is checked,
   * but in here also variables and if pipeline requires them is checked.
   * Validation also checks remote files presence in exercise database entity.
   * @param Exercise $exercise
   * @param ExerciseConfig $config
   * @throws ExerciseConfigException
   */
  public function validateExerciseConfig(Exercise $exercise, ExerciseConfig $config) {
    $this->exerciseConfigValidator->validate($config, $exercise);
  }

  /**
   * Validation of exercise limits, limits are defined for boxes which comes
   * from pipelines, identification of pipelines is taken from
   * exercise configuration, after that box identifications are checked if
   * existing.
   * @param Exercise $exercise
   * @param ExerciseLimits $limits
   * @param string $environmentId
   * @throws ExerciseConfigException
   */
  public function validateExerciseLimits(Exercise $exercise, ExerciseLimits $limits) {
    $exerciseConfig = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    $this->exerciseLimitsValidator->validate($limits, $exerciseConfig);
  }

}
