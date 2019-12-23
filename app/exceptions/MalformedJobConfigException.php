<?php

namespace App\Exceptions;

use Nette\Http\IResponse;
use App\Helpers\YamlException;

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
   * @param YamlException $originalException Optional pointer to original exception of YAML parser.
   *                                          Line numbers and snippets can be found there.
   * @param string $frontendErrorCode
   * @param null $frontendErrorParams
   */
  public function __construct(
    string $msg = 'Please contact your supervisor',
    YamlException $originalException = null,
    string $frontendErrorCode = FrontendErrorMappings::E400_200__JOB_CONFIG,
    $frontendErrorParams = null
  ) {
    parent::__construct("Job configuration is malformed - $msg", IResponse::S400_BAD_REQUEST, $frontendErrorCode, $frontendErrorParams);
    $this->originalException = $originalException;
  }

  /**
   * Get original exception of YAML parser
   */
  public function getOriginalException(): YamlException {
    return $this->originalException;
  }

}
