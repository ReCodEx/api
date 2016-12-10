<?php

namespace App\Exceptions;

use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Used when configuration cannot be loaded from file, or given text is
 * not a valid YAML document.
 */
class MalformedJobConfigException extends ApiException {
  /**
   * Original parsing exception of YAML job config
   */
  private $originalException;

  /**
   * Creates instance with further description.
   * @param string $msg description
   * @param ParseException $originalException Optional pointer to original exception of YAML parser.
   *                                          Line numbers and snippets can be found there.
   */
  public function __construct(string $msg = 'Please contact your supervisor', ParseException $originalException = NULL) {
    parent::__construct("Job configuration is malformed - $msg");
    $this->originalException = $originalException;
  }

  /**
   * Get original exception of YAML parser
   */
  public function getOriginalException() {
    return $this->originalException;
  }

}
