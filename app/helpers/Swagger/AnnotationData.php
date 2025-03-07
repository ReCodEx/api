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
    public ?string $endpointDescription;

    public function __construct(
        HttpMethods $httpMethod,
        array $pathParams,
        array $queryParams,
        array $bodyParams,
        string $endpointDescription = null,
    ) {
        $this->httpMethod = $httpMethod;
        $this->pathParams = $pathParams;
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
        $this->endpointDescription = $endpointDescription;
    }

    public function getAllParams(): array
    {
        return array_merge($this->pathParams, $this->queryParams, $this->bodyParams);
    }

    /**
     * Creates a method annotation string parsable by the swagger generator.
     * Example: if the method name is 'Put', the method will return '@OA\\PUT'.
     * @return string Returns the method annotation.
     */
    private function getHttpMethodAnnotation(): string
    {
        // sample: converts 'PUT' to 'Put'
        $httpMethodString = ucfirst(strtolower($this->httpMethod->name));
        return "@OA\\" . $httpMethodString;
    }

    /**
     * Creates a JSON request body annotation string parsable by the swagger generator.
     * Example: if the request body contains only the 'url' property, this method will produce:
     * '@OA\RequestBody(@OA\MediaType(mediaType="application/json",@OA\Schema(@OA\Property(property="url",type="string"))))'
     * @return string|null Returns the annotation string or null, if there are no body parameters.
     */
    private function getBodyAnnotation(): string | null
    {
        if (count($this->bodyParams) === 0) {
            return null;
        }

        // only json is supported due to the media type
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

        // add the endpoint description when provided
        if ($this->endpointDescription !== null) {
            $body->addKeyValue("summary", $this->endpointDescription);
            $body->addKeyValue("description", $this->endpointDescription);
        }

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

        ///TODO: A placeholder for the response type. This has to be replaced with the autogenerated meta-view
        /// response data structure in the future.
        $body->addValue('@OA\Response(response="200",description="The data")');
        return $httpMethodAnnotation . $body->toString();
    }
}
