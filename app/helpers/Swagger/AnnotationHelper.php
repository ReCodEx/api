<?php

namespace App\Helpers\Swagger;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\MetaFormatHelper;
use App\V1Module\Router\MethodRoute;
use App\V1Module\RouterFactory;
use Nette\Routing\RouteList;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Exception;
use InvalidArgumentException;

/**
 * Parser that can parse the annotations of existing recodex endpoints.
 */
class AnnotationHelper
{
    private static $nullableSuffix = '|null';
    private static $typeMap = [
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'array',
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'number',
        'number' => 'number',
        'numeric' => 'number',
        'numericint' => 'integer',
        'timestamp' => 'integer',
        'string' => 'string',
        'unicode' => 'string',
        'email' => 'string',
        'url' => 'string',
        'uri' => 'string',
        'pattern' => null,
        'alnum' => 'string',
        'alpha' => 'string',
        'digit' => 'string',
        'lower' => 'string',
        'upper' => 'string',
    ];

    private static $presenterNamespace = 'App\V1Module\Presenters\\';

    /**
     * Returns a ReflectionMethod object matching the name of the method and containing class.
     * @param string $className The name of the containing class.
     * @param string $methodName The name of the method.
     * @return \ReflectionMethod Returns the ReflectionMethod object.
     */
    public static function getMethod(string $className, string $methodName): ReflectionMethod
    {
        $class = new ReflectionClass($className);
        return $class->getMethod($methodName);
    }

    /**
     * Searches an array of annotations for any line starting with a valid HTTP method.
     * @param array $annotations An array of annotations.
     * @return \App\Helpers\Swagger\HttpMethods|null Returns the HTTP method or null if none present.
     */
    private static function extractAnnotationHttpMethod(array $annotations): HttpMethods | null
    {
        // get string names of the enumeration
        $cases = HttpMethods::cases();
        $methods = [];
        foreach ($cases as $case) {
            $methods["@{$case->name}"] = $case;
        }

        // check if the annotations have an http method
        foreach ($methods as $methodString => $methodEnum) {
            foreach ($annotations as $annotation) {
                if (str_starts_with($annotation, $methodString)) {
                    return $methodEnum;
                }
            }
        }

        return null;
    }

    private static function isDatatypeNullable(mixed $annotationType): bool
    {
        // if the dataType is not specified (it is null), it means that the annotation is not
        // complete and defaults to a non nullable string
        if ($annotationType === null) {
            return false;
        }

        // assumes that the typename contains 'null'
        if (str_contains($annotationType, "null")) {
            return true;
        }

        return false;
    }

    /**
     * Returns the swagger type associated with the annotation data type.
     * @return string Returns the name of the swagger type.
     */
    private static function getSwaggerType(string $annotationType): string
    {
        // if the type is not specified, default to a string
        $type = 'string';
        $typename = $annotationType;
        if ($typename !== null) {
            if (self::isDatatypeNullable($annotationType)) {
                $typename = substr($typename, 0, -strlen(self::$nullableSuffix));
            }

            if (self::$typeMap[$typename] === null) {
                throw new InvalidArgumentException("Error in getSwaggerType: Unknown typename: {$typename}");
            }

            $type = self::$typeMap[$typename];
        }
        return $type;
    }

    /**
     * Extracts standard doc comments from endpoints, such as '@param string $id An identifier'.
     * Based on the HTTP route of the endpoint, the extracted param can be identified as either a path or
     * query parameter.
     * @param array $annotations An array of annotations.
     * @param string $route The HTTP route of the endpoint.
     * @return array Returns an array of AnnotationParameterData objects describing the parameters.
     */
    private static function extractStandardAnnotationParams(array $annotations, string $route): array
    {
        $routeParams = self::getRoutePathParamNames($route);

        // does not see unannotated query params, but there are not any
        $params = [];
        foreach ($annotations as $annotation) {
            // assumed that all query parameters have a @param annotation
            if (str_starts_with($annotation, "@param")) {
                // sample: @param string $id Identifier of the user
                $tokens = explode(" ", $annotation);
                $annotationType = $tokens[1];
                // assumed that all names start with $
                $name = substr($tokens[2], 1);
                $description = implode(" ", array_slice($tokens, 3));

                // path params have to be required
                $isPathParam = false;
                // figure out where the parameter is located
                $location = 'query';
                if (in_array($name, $routeParams)) {
                    $location = 'path';
                    $isPathParam = true;
                    // remove the path param from the path param list to detect parameters left behind
                    // (this happens when the path param does not have an annotation line)
                    unset($routeParams[array_search($name, $routeParams)]);
                }

                $swaggerType = self::getSwaggerType($annotationType);
                $nullable = self::isDatatypeNullable($annotationType);

                // the array element type cannot be determined from standard @param annotations
                $nestedArraySwaggerType = null;
                // the actual depth of the array cannot be determined as well
                $arrayDepth = null;
                if ($swaggerType == "array") {
                    $arrayDepth = 1;
                }

                $descriptor = new AnnotationParameterData(
                    $swaggerType,
                    $name,
                    $description,
                    $location,
                    $isPathParam,
                    $nullable,
                    nestedArraySwaggerType: $nestedArraySwaggerType,
                    arrayDepth: $arrayDepth,
                );
                $params[] = $descriptor;
            }
        }

        // handle path params without annotations
        foreach ($routeParams as $pathParam) {
            $descriptor = new AnnotationParameterData(
                // some type needs to be assigned and string seems reasonable for a param without any info
                "string",
                $pathParam,
                null,
                "path",
                true,
                false,
            );
            $params[] = $descriptor;
        }

        return $params;
    }

    /**
     * Parses an annotation string and returns the lines as an array.
     * Lines not starting with '@' are assumed to be continuations of a parent line starting with @ (or the initial
     * line not starting with '@') and are merged into a single line.
     * @param string $annotations The annotation string.
     * @return array Returns an array of the annotation lines.
     */
    public static function getAnnotationLines(string $annotations): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $annotations);

        // trims whitespace and asterisks
        // assumes that asterisks are not used in some meaningful way at the beginning and end of a line
        foreach ($lines as &$line) {
            $line = trim($line);
            $line = trim($line, "*");
            $line = trim($line);
        }

        // removes the first and last line
        // assumes that the first line is '/**' and the last line '*/' (or '/' after trimming)
        $lines = array_slice($lines, 1, -1);

        $merged = [];
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            // skip lines not starting with '@'
            // also do not skip the first description line
            if ($i != 0 && $line[0] !== "@") {
                continue;
            }

            // merge lines not starting with '@' with their parent lines starting with '@'
            while ($i + 1 < count($lines) && $lines[$i + 1][0] !== "@") {
                $line .= " " . $lines[$i + 1];
                $i++;
            }

            $merged[] = $line;
        }

        return $merged;
    }

    /**
     * Returns all method annotation lines as an array.
     * Lines not starting with '@' are assumed to be continuations of a parent line starting with @ (or the initial
     * line not starting with '@') and are merged into a single line.
     * @param string $className The name of the containing class.
     * @param string $methodName The name of the method.
     * @return array Returns an array of the annotation lines.
     */
    public static function getMethodAnnotations(string $className, string $methodName): array
    {
        $annotations = self::getMethod($className, $methodName)->getDocComment();
        return self::getAnnotationLines($annotations);
    }

    /**
     * Extracts strings enclosed by curly brackets.
     * @param string $route The source string.
     * @return array Returns the tokens extracted from the brackets.
     */
    private static function getRoutePathParamNames(string $route): array
    {
        // sample: from '/users/{id}/{name}' generates ['id', 'name']
        preg_match_all('/\{([A-Za-z0-9 ]+?)\}/', $route, $out);
        return $out[1];
    }

    /**
     * Extracts the annotation description line.
     * @param array $annotations The array of annotations.
     */
    private static function extractAnnotationDescription(array $annotations): ?string
    {
        // it is either the first line (already merged if multiline), or none at all
        if (!str_starts_with($annotations[0], "@")) {
            return $annotations[0];
        }
        return null;
    }

    private static function annotationParameterDataToAnnotationData(
        string $className,
        string $methodName,
        HttpMethods $httpMethod,
        array $params,
        array $responseDataList,
        ?string $description,
    ): AnnotationData {
        $pathParams = [];
        $queryParams = [];
        $bodyParams = [];
        $fileParams = [];

        foreach ($params as $param) {
            if ($param->location === 'path') {
                $pathParams[] = $param;
            } elseif ($param->location === 'query') {
                $queryParams[] = $param;
            } elseif ($param->location === 'post') {
                $bodyParams[] = $param;
            } elseif ($param->location === 'file') {
                $fileParams[] = $param;
            } else {
                throw new Exception("Error in extractAnnotationData: Unknown param location: {$param->location}");
            }
        }

        return new AnnotationData(
            $className,
            $methodName,
            $httpMethod,
            $pathParams,
            $queryParams,
            $bodyParams,
            $fileParams,
            $responseDataList,
            $description,
        );
    }

    /**
     * Extracts standard (@param) annotation data of an endpoint. The data contains request parameters based
     *  on their type and the HTTP method.
     * @param string $className The name of the containing class.
     * @param string $methodName The name of the endpoint method.
     * @param string $route The route to the method.
     * @throws Exception Thrown when the parser encounters an unknown parameter location (known locations are
     * path, query and post)
     * @return \App\Helpers\Swagger\AnnotationData Returns a data object containing the parameters and HTTP method.
     */
    public static function extractStandardAnnotationData(
        string $className,
        string $methodName,
        string $route
    ): AnnotationData {
        $methodAnnotations = self::getMethodAnnotations($className, $methodName);

        $httpMethod = self::extractAnnotationHttpMethod($methodAnnotations);
        $params = self::extractStandardAnnotationParams($methodAnnotations, $route);
        $description = self::extractAnnotationDescription($methodAnnotations);

        return self::annotationParameterDataToAnnotationData(
            $className,
            $methodName,
            $httpMethod,
            $params,
            [], // there are no reponse params defined in the old annotations
            $description
        );
    }

    /**
     * Extracts the attribute data of an endpoint. The data contains request parameters based on their type
     * and the HTTP method.
     * @param string $className The name of the containing class.
     * @param string $methodName The name of the endpoint method.
     * @throws Exception Thrown when the parser encounters an unknown parameter location (known locations are
     * path, query and post)
     * @return \App\Helpers\Swagger\AnnotationData Returns a data object containing the parameters and HTTP method.
     */
    public static function extractAttributeData(string $className, string $methodName): AnnotationData
    {
        $methodAnnotations = self::getMethodAnnotations($className, $methodName);

        $httpMethod = self::extractAnnotationHttpMethod($methodAnnotations);
        $reflectionMethod = self::getMethod($className, $methodName);

        // extract loose attributes
        $attributeData = MetaFormatHelper::extractRequestParamData($reflectionMethod);

        // if the endpoint is linked to a format, add the format class attributes
        $format = MetaFormatHelper::extractFormatFromAttribute($reflectionMethod);
        if ($format !== null) {
            $attributeData = array_merge($attributeData, FormatCache::getFieldDefinitions($format));
        }

        // if the endpoint uses response formats, extract their parameters
        $responseAttributes = MetaFormatHelper::extractResponseFormatFromAttribute($reflectionMethod);
        $responseDataList = [];
        $statusCodes = [];
        foreach ($responseAttributes as $responseAttribute) {
            $responseFieldDefinitions = FormatCache::getFieldDefinitions($responseAttribute->format);
            $responseParams = array_map(function ($data) {
                return $data->toAnnotationParameterData();
            }, $responseFieldDefinitions);

            // check if all response status codes are unique
            if (array_key_exists($responseAttribute->statusCode, $statusCodes)) {
                throw new InternalServerException(
                    "The method " . $reflectionMethod->name . " contains duplicate response codes."
                );
            }
            $statusCodes[] = $responseAttribute->statusCode;

            $responseData = new ResponseData(
                $responseParams,
                $responseAttribute->description,
                $responseAttribute->statusCode,
                $responseAttribute->useSuccessWrapper
            );

            $responseDataList[] = $responseData;
        }

        $params = array_map(function ($data) {
            return $data->toAnnotationParameterData();
        }, $attributeData);
        $description = self::extractAnnotationDescription($methodAnnotations);

        return self::annotationParameterDataToAnnotationData(
            $className,
            $methodName,
            $httpMethod,
            $params,
            $responseDataList,
            $description,
        );
    }

    public static function extractAttributeDataTesting(string $className, string $methodName): array
    {
        $methodAnnotations = self::getMethodAnnotations($className, $methodName);

        $httpMethod = self::extractAnnotationHttpMethod($methodAnnotations);
        $reflectionMethod = self::getMethod($className, $methodName);

        // extract loose attributes
        $attributeData = MetaFormatHelper::extractRequestParamData($reflectionMethod);

        // if the endpoint is linked to a format, add the format class attributes
        $format = MetaFormatHelper::extractFormatFromAttribute($reflectionMethod);
        if ($format !== null) {
            $attributeData = array_merge($attributeData, FormatCache::getFieldDefinitions($format));
        }

        return [
            "httpMethod" => $httpMethod,
            "data" => $attributeData,
        ];
    }

    /**
     * Filters annotation lines starting with a prefix.
     * @param array $annotations An array of annotations.
     * @param string $type The prefix with which the lines should start, such as '@param'.
     * @return array Returns an array of filtered annotations.
     */
    public static function filterAnnotations(array $annotations, string $type)
    {
        $rows = [];
        foreach ($annotations as $annotation) {
            if (str_starts_with($annotation, $type)) {
                $rows[] = $annotation;
            }
        }
        return $rows;
    }

    /**
     * Finds all route objects of the API and returns their metadata.
     * @return array Returns an array of dictionaries with the keys "route", "class", and "method".
     */
    public static function getRoutesMetadata(): array
    {
        $router = RouterFactory::createRouter();

        // find all route object using a queue
        $queue = [$router];
        $routes = [];
        while (count($queue) != 0) {
            $cursor = array_shift($queue);

            if ($cursor instanceof RouteList) {
                foreach ($cursor->getRouters() as $item) {
                    // lists contain routes or nested lists
                    if ($item instanceof RouteList) {
                        array_push($queue, $item);
                    } else {
                        // the first route is special and holds no useful information for annotation
                        if (get_parent_class($item) !== MethodRoute::class) {
                            continue;
                        }

                        $routes[] = self::getPropertyValue($item, "route");
                    }
                }
            }
        }


        $routeMetadata = [];
        foreach ($routes as $routeObj) {
            // extract class and method names of the endpoint
            $metadata = self::extractMetadata($routeObj);
            $route = self::extractRoute($routeObj);
            $className = self::$presenterNamespace . $metadata['class'];
            $methodName = $metadata['method'];

            $routeMetadata[] = [
                "route" => $route,
                "class" => $className,
                "method" => $methodName,
            ];
        }

        return $routeMetadata;
    }

    /**
     * Helper function that can extract a property value from an arbitrary object where
     * the property can be private.
     * @param mixed $object The object to extract from.
     * @param string $propertyName The name of the property.
     * @return mixed Returns the value of the property.
     */
    public static function getPropertyValue(mixed $object, string $propertyName): mixed
    {
        $class = new ReflectionClass($object);

        do {
            try {
                $property = $class->getProperty($propertyName);
            } catch (ReflectionException $exception) {
                $class = $class->getParentClass();
                $property = null;
            }
        } while ($property === null && $class !== null);

        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Extracts the route string from a route object. Replaces '<..>' in the route with '{...}'.
     * @param mixed $routeObj
     */
    private static function extractRoute($routeObj): string
    {
        $mask = AnnotationHelper::getPropertyValue($routeObj, "mask");

        // sample: replaces '/users/<id>' with '/users/{id}'
        $mask = str_replace(["<", ">"], ["{", "}"], $mask);
        return "/" . $mask;
    }

    /**
     * Extracts the class and method names of the endpoint handler.
     * @param mixed $routeObj The route object representing the endpoint.
     * @return string[] Returns a dictionary [ "class" => ..., "method" => ...]
     */
    private static function extractMetadata($routeObj)
    {
        $metadata = AnnotationHelper::getPropertyValue($routeObj, "metadata");
        $presenter = $metadata["presenter"]["value"];
        $action = $metadata["action"]["value"];

        // if the name is empty, the method will be called 'actionDefault'
        if ($action === null) {
            $action = "default";
        }

        return [
            "class" => $presenter . "Presenter",
            "method" => "action" . ucfirst($action),
        ];
    }
}
