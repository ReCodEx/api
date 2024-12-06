<?php

namespace App\Helpers\Swagger;

// PSR12 does not handle enums well
// @codingStandardsIgnoreStart
enum HttpMethods
{
    case GET;
    case POST;
    case PUT;
    case DELETE;
}
// @codingStandardsIgnoreEnd
