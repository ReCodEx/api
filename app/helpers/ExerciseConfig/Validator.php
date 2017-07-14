<?php

namespace App\Helpers\ExerciseConfig;

use App\Exceptions\ExerciseConfigException;
use App\Model\Entity\Exercise;
use App\Model\Repository\Pipelines;


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
   * @var Pipelines
   */
  private $pipelines;

  /**
   * Validator constructor.
   * @param Pipelines $pipelines
   */
  public function __construct(Pipelines $pipelines) {
    $this->pipelines = $pipelines;
  }


  /**
   * Validate pipeline, all input ports have to have specified either variable
   * reference or textual value. Variable reference has to be used only
   * two times, one should point to input port and second one to output port.
   * Exception is if variable reference is specified only in output port, then
   * this variable does not have to be used in any input port.
   * @param Pipeline $pipeline
   * @throws ExerciseConfigException
   */
  public function validatePipeline(Pipeline $pipeline) {
    // @todo
  }

  /**
   * Validation of exercise configuration against environment configurations,
   * that means mainly runtime environment identification. Another checks are
   * made against pipeline, again identification of pipeline is checked,
   * but in here also variables and if pipeline requires them is checked.
   * @param Exercise $exercise
   * @param ExerciseConfig $config
   * @throws ExerciseConfigException
   */
  public function validateExerciseConfig(Exercise $exercise, ExerciseConfig $config) {
    // @todo
  }

  /**
   * Validation of exercise limits, limits are defined for boxes which comes
   * from pipelines, identification of pipelines is taken from
   * exercise configuration, after that box identifications are checked if
   * existing.
   * @param Exercise $exercise
   * @param ExerciseLimits $limits
   * @throws ExerciseConfigException
   */
  public function validateExerciseLimits(Exercise $exercise, ExerciseLimits $limits) {
    // @todo
  }

}
