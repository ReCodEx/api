<?php

namespace App\Helpers\Swagger;

use ReflectionClass;
use Exception;

/**
 * Parser that can parse the annotations of existing recodex endpoints.
 */
class AnnotationHelper
{
    private static function getMethod(string $className, string $methodName): \ReflectionMethod
    {
        $class = new ReflectionClass($className);
        return $class->getMethod($methodName);
    }

    private static function extractAnnotationHttpMethod(array $annotations): HttpMethods | null
    {
        // get string values of backed enumeration
        $cases = HttpMethods::cases();
        $methods = [];
        foreach ($cases as $case) {
            $methods["@{$case->name}"] = $case;
        }

        // check if the annotations have an http method
        foreach ($methods as $methodString => $methodEnum) {
            if (in_array($methodString, $annotations)) {
                return $methodEnum;
            }
        }

        return null;
    }

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

    private static function extractBodyParams(array $expressions): array
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
                $values = self::extractBodyParams($tokens);
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

    private static function getMethodAnnotations(string $className, string $methodName): array
    {
        $annotations = self::getMethod($className, $methodName)->getDocComment();
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

    private static function getRoutePathParamNames(string $route): array
    {
        // sample: from '/users/{id}/{name}' generates ['id', 'name']
        preg_match_all('/\{([A-Za-z0-9 ]+?)\}/', $route, $out);
        return $out[1];
    }

    public static function extractAnnotationData(string $className, string $methodName, string $route): AnnotationData
    {
        $methodAnnotations = self::getMethodAnnotations($className, $methodName);

        $httpMethod = self::extractAnnotationHttpMethod($methodAnnotations);
        $standardAnnotationParams = self::extractStandardAnnotationParams($methodAnnotations, $route);
        $netteAnnotationParams = self::extractNetteAnnotationParams($methodAnnotations);
        $params = array_merge($standardAnnotationParams, $netteAnnotationParams);

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

    private static function filterAnnotations(array $annotations, string $type)
    {
        $rows = [];
        foreach ($annotations as $annotation) {
            if (str_starts_with($annotation, $type)) {
                $rows[] = $annotation;
            }
        }
        return $rows;
    }

    private static function extractFormatData(array $annotations): array
    {
        $formats = [];
        $filtered = self::filterAnnotations($annotations, "@format_def");
        foreach ($filtered as $annotation) {
            // sample: @format user_info { "name":"format:name", "points":"format:int", "comments":"format:string[]" }
            $tokens = explode(" ", $annotation);
            $name = $tokens[1];
            
            $jsonStart = strpos($annotation, "{");
            $json = substr($annotation, $jsonStart);
            $format = json_decode($json);

            $formats[$name] = $format;
        }
        return $formats;
    }

    private static function extractMethodFormats(string $className, string $methodName): array
    {
        $annotations = self::getMethodAnnotations($className, $methodName);
        return self::extractFormatData($annotations);
    }

    public static function extractClassFormats(string $className): array
    {
        $methods = get_class_methods($className);
        $formatDicts = [];
        foreach ($methods as $method) {
            $formatDicts[] = self::extractMethodFormats($className, $method);
        }

        return array_merge(...$formatDicts);
    }

    public static function extractMethodCheckedParams(string $className, string $methodName): array
    {
        $annotations = self::getMethodAnnotations($className, $methodName);
        $filtered = self::filterAnnotations($annotations, "@checked_param");
        
        $paramMap = [];
        foreach ($filtered as $annotation) {
            // sample: @checked_param format:group group
            $tokens = explode(" ", $annotation);
            $format = $tokens[1];
            $name = $tokens[2];
            $paramMap[$name] = $format;
        }

        return $paramMap;
    }
}
