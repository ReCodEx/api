<?php
// phpcs:ignoreFile
// PSR12 does not handle enums well

namespace App\Helpers\Swagger;

enum HttpMethods
{
    case GET;
    case POST;
    case PUT;
    case DELETE;
}
