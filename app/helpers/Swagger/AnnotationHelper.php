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
                $type = $tokens[1];
                // assumed that all names start with $
                $name = substr($tokens[2], 1);
                $description = implode(" ", array_slice($tokens, 3));

                // figure out where the parameter is located
                $location = 'query';
                if (in_array($name, $routeParams)) {
                    $location = 'path';
                }

                $descriptor = new AnnotationParameterData($type, $name, $description, $location);
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
     * Extracts annotation parameter data from Nette annotations starting with the '@Param' prefix.
     * @param array $annotations An array of annotations.
     * @return array Returns an array of AnnotationParameterData objects describing the parameters.
     */
    private static function extractNetteAnnotationParams(array $annotations): array
    {
        $bodyParams = [];
        $prefix = "@Param";
        foreach ($annotations as $annotation) {
            // assumed that all body parameters have a @Param annotation
            if (str_starts_with($annotation, $prefix)) {
                // sample: @Param(type="post", name="uiData", validation="array|null",
                //      description="Structured user-specific UI data")
                // remove '@Param(' from the start and ')' from the end
                $body = substr($annotation, strlen($prefix) + 1, -1);
                $tokens = explode(", ", $body);
                $values = self::stringArrayToAssociativeArray($tokens);
                $descriptor = new AnnotationParameterData(
                    $values["validation"],
                    $values["name"],
                    $values["description"],
                    $values["type"]
                );
                $bodyParams[] = $descriptor;
            }
        }
        return $bodyParams;
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
            if ($line[0] !== "@") {
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


        $data = new AnnotationData($httpMethod, $pathParams, $queryParams, $bodyParams);
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
