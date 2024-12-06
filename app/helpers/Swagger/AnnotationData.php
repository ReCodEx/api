<?php

namespace App\Helpers\Swagger;

/**
 * A data structure for endpoint signatures that can produce annotations parsable by a swagger generator.
 */
class AnnotationData
{
    public HttpMethods $httpMethod;
  
    public array $pathParams;
    public array $queryParams;
    public array $bodyParams;

    public function __construct(
        HttpMethods $httpMethod,
        array $pathParams,
        array $queryParams,
        array $bodyParams
    ) {
        $this->httpMethod = $httpMethod;
        $this->pathParams = $pathParams;
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
    }

    private function getHttpMethodAnnotation(): string
    {
        // sample: converts 'PUT' to 'Put'
        $httpMethodString = ucfirst(strtolower($this->httpMethod->name));
        return "@OA\\" . $httpMethodString;
    }

    private function getBodyAnnotation(): string | null
    {
        if (count($this->bodyParams) === 0) {
            return null;
        }

        ///TODO: only supports JSON
        $head = '@OA\RequestBody(@OA\MediaType(mediaType="application/json",@OA\Schema';
        $body = new ParenthesesBuilder();

        foreach ($this->bodyParams as $bodyParam) {
            $body->addValue($bodyParam->toPropertyAnnotation());
        }

        return $head . $body->toString() . "))";
    }

  /**
   * Converts the extracted annotation data to a string parsable by the Swagger-PHP library.
   * @param string $route The route of the handler this set of data represents.
   * @return string Returns the transpiled annotations on a single line.
   */
    public function toSwaggerAnnotations(string $route)
    {
        $httpMethodAnnotation = $this->getHttpMethodAnnotation();
        $body = new ParenthesesBuilder();
        $body->addKeyValue("path", $route);

        foreach ($this->pathParams as $pathParam) {
            $body->addValue($pathParam->toParameterAnnotation());
        }
        foreach ($this->queryParams as $queryParam) {
            $body->addValue($queryParam->toParameterAnnotation());
        }

        $jsonProperties = $this->getBodyAnnotation();
        if ($jsonProperties !== null) {
            $body->addValue($jsonProperties);
        }

        ///TODO: placeholder
        $body->addValue('@OA\Response(response="200",description="The data")');
        return $httpMethodAnnotation . $body->toString();
    }
}
