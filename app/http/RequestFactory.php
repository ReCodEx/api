<?php

namespace App;

use Nette;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use App\Exceptions\BadRequestException;

/**
 * Main router factory which is used to create all possible routes.
 * @return Nette\Http\Request
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
        if (
            !empty($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) == 'POST'
            && !empty($_SERVER['CONTENT_TYPE']) && strpos(
                strtolower($_SERVER['CONTENT_TYPE']),
                'application/json'
            ) !== false
        ) {
            $body = file_get_contents('php://input');
            try {
                $json = Json::decode($body, Json::FORCE_ARRAY);
            } catch (JsonException $e) {
                throw new BadRequestException("Parsing of the JSON body failed: " . $e->getMessage());
            }

            if (!is_array($json)) {
                throw new BadRequestException("A collection is expected as JSON body. Scalar value was given instead.");
            }

            $_POST = $json;
        }

        return parent::createHttpRequest();
    }
}
