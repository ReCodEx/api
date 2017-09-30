<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;


/**
 * Abstract class representing exporting resource from pipeline.
 */
abstract class DataOutBox extends Box
{

  /**
   * DataOutBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }

}
