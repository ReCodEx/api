<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;


/**
 * Box which represents data source, mainly files.
 */
class DataBox extends Box
{
  /**
   * DataBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }


  public function validateMetadata() {
    // TODO: Implement validateMetadata() method.
  }

  public function fillDefaults() {
    // TODO: Implement fillDefaults() method.
  }

}
