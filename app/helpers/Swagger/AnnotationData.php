<?php

namespace App\Helpers\Swagger;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\AnnotationConversion\Utils;
use App\Helpers\MetaFormats\FileRequestType;

/**
 * A data structure for endpoint signatures that can produce annotations parsable by a swagger generator.
 */
class AnnotationData
{
    public HttpMethods $httpMethod;

    public string $className;
    public string $methodName;
    /**
     * @var AnnotationParameterData[]
     */
    public array $pathParams;
    /**
     * @var AnnotationParameterData[]
     */
    public array $queryParams;
    /**
     * @var AnnotationParameterData[]
     */
    public array $bodyParams;
    /**
     * @var AnnotationParameterData[]
     */
    public array $fileParams;
    /**
     * @var ResponseData[]
     */
    public array $responseDataList;
    public ?string $endpointDescription;

    public function __construct(
        string $className,
        string $methodName,
        HttpMethods $httpMethod,
        array $pathParams,
        array $queryParams,
        array $bodyParams,
        array $fileParams,
        array $responseDataList,
        ?string $endpointDescription = null,
    ) {
        $this->className = $className;
        $this->methodName = $methodName;
        $this->httpMethod = $httpMethod;
        $this->pathParams = $pathParams;
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
        $this->fileParams = $fileParams;
        $this->responseDataList = $responseDataList;
        $this->endpointDescription = $endpointDescription;
    }

    public function getAllParams(): array
    {
        return array_merge($this->pathParams, $this->queryParams, $this->bodyParams, $this->fileParams);
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
     * Creates a requestBody annotation string parsable by the swagger generator.
     * Processes JSON request body and files (form-data, octet-stream).
     * @return string|null Returns the annotation string or null, if there is no body.
     */
    private function getBodyAnnotation(): string | null
    {
        $head = '@OA\RequestBody';
        $body = new ParenthesesBuilder();

        // add the json schema
        $jsonSchema = $this->serializeBodyParams(
            "application/json",
            $this->bodyParams
        );
        if ($jsonSchema !== null) {
            $body->addValue($jsonSchema);
        }

        // add the file schema
        $fileSchema = $this->getFileAnnotation();
        if ($fileSchema !== null) {
            $body->addValue($fileSchema);
        }

        if ($jsonSchema === null && $fileSchema === null) {
            return null;
        }

        return $head . $body->toString();
    }

    private function getFileAnnotation(): string | null
    {
        if (count($this->fileParams) === 0) {
            return null;
        }

        // filter file params based on type
        $formParams = [];
        $octetParams = [];
        foreach ($this->fileParams as $fileParam) {
            if ($fileParam->fileRequestType === FileRequestType::FormData) {
                $formParams[] = $fileParam;
            } elseif ($fileParam->fileRequestType === FileRequestType::OctetStream) {
                $octetParams[] = $fileParam;
            } elseif ($fileParam->fileRequestType === null) {
                throw new InternalServerException("The FileRequestType is null");
            } else {
                throw new InternalServerException("Unknown FileRequestType: " . $fileParam->fileRequestType->name);
            }
        }

        if (count($formParams) > 0 && count($octetParams) > 0) {
            throw new InternalServerException("File requests cannot upload files as both form-data and octet-stream.");
        }
        if (count($octetParams) > 1) {
            throw new InternalServerException("There can only be one octet-stream per request.");
        }

        // generate a form-data or octet-stream annotation
        if (count($formParams) > 0) {
            return $this->serializeBodyParams("multipart/form-data", $formParams);
        } else {
            return $this->getOctetStreamAnnotation($octetParams[0]);
        }
    }

    /**
     * Creates a content annotation string parsable by the swagger generator.
     * Example: if a JSON request body contains only the 'url' property, this method will produce:
     * '@OA\MediaType(mediaType="application/json",@OA\Schema(@OA\Property(property="url",type="string")))'
     * @param string $mediaType The media type of the parameters ("application/json", "multipart/form-data").
     * @param array $bodyParams AnnotationParameterData array used to generate the annotation.
     * @return string|null Returns the annotation string or null, if there are no body parameters.
     */
    private function serializeBodyParams(string $mediaType, array $bodyParams): string | null
    {
        if (count($bodyParams) === 0) {
            return null;
        }

        $head = '@OA\MediaType(mediaType="' . $mediaType . '",@OA\Schema';
        $body = new ParenthesesBuilder();
        // list of all required properties
        $required = [];

        foreach ($bodyParams as $bodyParam) {
            $body->addValue($bodyParam->toPropertyAnnotation());
            if ($bodyParam->required) {
                // add quotes around the names (required by the swagger generator)
                $required[] = '"' . $bodyParam->name . '"';
            }
        }

        // add a list of required properties
        if (count($required) > 0) {
            // stringify the list (it has to be in '{"name1","name1",...}' format)
            $requiredString = "{" . implode(",", $required) . "}";
            $body->addValue("required=" . $requiredString);
        }

        return $head . $body->toString() . ")";
    }

    private function getOctetStreamAnnotation(AnnotationParameterData $octetParam): string
    {
        $head = '@OA\MediaType(mediaType="application/octet-stream",@OA\Schema';
        $body = new ParenthesesBuilder();

        $body->addKeyValue("type", $octetParam->swaggerType);
        $body->addKeyValue("format", "binary");
        return $head . $body->toString() . ")";
    }

    /**
     * Constructs an operation ID used to identify the endpoint.
     * The operation ID is composed of the presenter class name and the endpoint method name with the 'action' prefix.
     * @return string Returns the operation ID.
     */
    private function constructOperationId()
    {
        // remove the namespace prefix of the class and make the first letter lowercase
        $className = lcfirst(Utils::shortenClass($this->className));
        // make the 'a' in the action prefix uppercase to match the camel-case notation
        $endpoint = ucfirst($this->methodName);
        return $className . $endpoint;
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
        $body->addKeyValue("operationId", $this->constructOperationId());

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

        $bodyProperties = $this->getBodyAnnotation();
        if ($bodyProperties !== null) {
            $body->addValue($bodyProperties);
        }

        // generate responses
        foreach ($this->responseDataList as $responseData) {
            $responseSchema = $this->serializeBodyParams(
                "application/json",
                $responseData->responseParams,
            );

            $responseBody = new ParenthesesBuilder();
            $responseBody->addKeyValue("response", $responseData->statusCode);
            $responseBody->addKeyValue("description", $responseData->description);
            $responseBody->addValue($responseSchema);

            $body->addValue("@OA\Response" . $responseBody->toString());
        }

        // add a placeholder response if none present
        if (count($this->responseDataList) === 0) {
            $body->addValue('@OA\Response(response="200",description="Placeholder response")');
        }

        return $httpMethodAnnotation . $body->toString();
    }
}
