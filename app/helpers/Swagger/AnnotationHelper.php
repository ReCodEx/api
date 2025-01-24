<?php

namespace App\Helpers\Swagger;

use App\Helpers\MetaFormats\MetaFormatHelper;
use ReflectionClass;
use ReflectionMethod;
use Exception;

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

    private static function isDatatypeNullable(string $annotationType): bool
    {
        // if the dataType is not specified (it is null), it means that the annotation is not
        // complete and defaults to a non nullable string
        if ($annotationType === null) {
            return false;
        }

        // assumes that the typename ends with '|null'
        if (str_ends_with($annotationType, self::$nullableSuffix)) {
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
                throw new \InvalidArgumentException("Error in getSwaggerType: Unknown typename: {$typename}");
            }

            $type = self::$typeMap[$typename];
        }
        return $type;
    }

    /**
     * Extracts standart doc comments from endpoints, such as '@param string $id An identifier'.
     * Based on the HTTP route of the endpoint, the extracted param can be identified as either a path or
     * query parameter.
     * @param array $annotations An array of annotations.
     * @param string $route The HTTP route of the endpoint.
     * @return array Returns an array of AnnotationParameterData objects describing the parameters.
     */
    private static function extractStandardAnnotationParams(array $annotations, string $route): array
    {
        $routeParams = self::getRoutePathParamNames($route);

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
                }

                $swaggerType = self::getSwaggerType($annotationType);
                $nullable = self::isDatatypeNullable($annotationType);

                ///TODO: how to find out the correct query type?
                $nestedArraySwaggerType = null;

                $descriptor = new AnnotationParameterData(
                    $swaggerType,
                    $name,
                    $description,
                    $location,
                    $isPathParam,
                    $nullable,
                    nestedArraySwaggerType: $nestedArraySwaggerType,
                );
                $params[] = $descriptor;
            }
        }
        return $params;
    }

    /**
     * Converts an array of assignment string to an associative array.
     * @param array $expressions An array containing values in the following format: 'key="value"'.
     * @return array Returns an associative array made from the string array.
     */
    private static function stringArrayToAssociativeArray(array $expressions): array
    {
        $dict = [];
        //sample: [ 'name="uiData"', 'validation="array|null"' ]
        foreach ($expressions as $expression) {
            $tokens = explode('="', $expression);
            $name = $tokens[0];
            // remove the '"' at the end
            $value = substr($tokens[1], 0, -1);
            $dict[$name] = $value;
        }
        return $dict;
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

    /**
     * Extracts the annotation data of an endpoint. The data contains request parameters based on their type
     * and the HTTP method.
     * @param string $className The name of the containing class.
     * @param string $methodName The name of the endpoint method.
     * @param string $route The route to the method.
     * @throws Exception Thrown when the parser encounters an unknown parameter location (known locations are
     * path, query and post)
     * @return \App\Helpers\Swagger\AnnotationData Returns a data object containing the parameters and HTTP method.
     */
    public static function extractAnnotationData(string $className, string $methodName, string $route): AnnotationData
    {
        $methodAnnotations = self::getMethodAnnotations($className, $methodName);

        $httpMethod = self::extractAnnotationHttpMethod($methodAnnotations);
        $standardAnnotationParams = self::extractStandardAnnotationParams($methodAnnotations, $route);
        $attributeData = MetaFormatHelper::extractRequestParamData(self::getMethod($className, $methodName));
        $attributeParams = array_map(function ($data) {
            return $data->toAnnotationParameterData();
        }, $attributeData);
        $params = array_merge($standardAnnotationParams, $attributeParams);

        $pathParams = [];
        $queryParams = [];
        $bodyParams = [];

        foreach ($params as $param) {
            if ($param->location === 'path') {
                $pathParams[] = $param;
            } elseif ($param->location === 'query') {
                $queryParams[] = $param;
            } elseif ($param->location === 'post') {
                $bodyParams[] = $param;
            } else {
                throw new Exception("Error in extractAnnotationData: Unknown param location: {$param->location}");
            }
        }

        $description = self::extractAnnotationDescription($methodAnnotations);

        $data = new AnnotationData($httpMethod, $pathParams, $queryParams, $bodyParams, $description);
        return $data;
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
}
