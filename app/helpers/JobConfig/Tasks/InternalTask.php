<?php

namespace App\Helpers\JobConfig\Tasks;


/**
 * Empty shell above TaskBase which adds nothing to its functionality.
 */
class InternalTask extends TaskBase {

  /**
   * Redirect given structured job configuration to parent constructor.
   * @param array $data structured job config
   */
  public function __construct(array $data) {
    parent::__construct($data);
  }
}
