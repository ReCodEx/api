<?php

namespace App;

use Nette;

use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InternalServerErrorException;

/**
 * Main router factory which is used to create all possible routes.
 * @return Request
 */
class RequestFactory extends Nette\Http\RequestFactory
{
  /**
   * Overriding existing factory function to properly process JSON bodies instead of POST multipart.
   */
  public function createHttpRequest()
  {
    /*
     * A patch which will take JSON bodies passed in POST requests and fill them into $_POST array,
     * so they are parsed by create HTTP request method.
     */
    if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST'
      && strpos(strtolower($_SERVER['CONTENT_TYPE']), 'application/json') !== false) {
      $body = file_get_contents('php://input');
      $json = json_decode($body, true); // true = use assoc arrays instead of objects
      if ($json === null) {
        throw new BadRequestException("Parsing of the JSON body failed: " . json_last_error());
      }

      $_POST = $json;
    }

    return parent::createHttpRequest();
  }
}
