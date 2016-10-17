<?php

namespace App\Exceptions;

/**
 * Used when configuration cannot be loaded from file, or given text is
 * not a valid YAML document.
 */
class MalformedJobConfigException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Please contact your supervisor') {
    parent::__construct("Job configuration file is malformed - $msg");
  }

}
