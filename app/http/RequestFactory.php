<?php

namespace App;

use Nette;
use Nette\Http\Request;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use App\Exceptions\BadRequestException;

/**
 * Request factory responsible for creating HTTP request object wrappers.
 * @return Request
 */
class RequestFactory extends Nette\Http\RequestFactory
{
    /**
     * Overriding existing factory function to properly process JSON bodies instead of POST multipart.
     */
    public function fromGlobals(): Request
    {
        /*
         * A patch which will take JSON bodies passed in POST requests and patch them in
         * post data field of the HTTP request wrapper.
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
                $json = $body ? Json::decode($body, Json::FORCE_ARRAY) : [];
            } catch (JsonException $e) {
                throw new BadRequestException("Parsing of the JSON body failed: " . $e->getMessage());
            }

            if (!is_array($json)) {
                throw new BadRequestException("A collection is expected as JSON body. Scalar value was given instead.");
            }
        }

        // If JSON body is present, replace the result of original HTTP request factory with new post data.
        if (!empty($json)) {
            $request = parent::fromGlobals();
            return new Request(
                $request->getUrl(),
                $json,
                $request->getFiles(),
                $request->getCookies(),
                $request->getHeaders(),
                $request->getMethod(),
                $request->getRemoteAddress(),
                $request->getRemoteHost(),
                function () {
                    return file_get_contents('php://input');
                }
            );
        } else {
            return parent::fromGlobals();
        }
    }
}
