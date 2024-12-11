<?php

namespace App\Helpers\Swagger;

use ReflectionClass;
use Exception;
use App\Helpers\Swagger\PrimitiveFormatValidators;

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


    private static function getAnnotationLines(string $annotations)
    {
        $lines = preg_split("/\r\n|\n|\r/", $annotations);

        # trims whitespace and asterisks
        # assumes that asterisks are not used in some meaningful way at the beginning and end of a line
        foreach ($lines as &$line) {
            $line = trim($line);
            $line = trim($line, "*");
            $line = trim($line);
        }

        # removes the first and last line
        # assumes that the first line is '/**' and the last line '*/' (or '/' after trimming)
        $lines = array_slice($lines, 1, -1);

        $merged = [];
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            # skip lines not starting with '@'
            if ($line[0] !== "@") {
                continue;
            }

            # merge lines not starting with '@' with their parent lines starting with '@'
            while ($i + 1 < count($lines) && $lines[$i + 1][0] !== "@") {
                $line .= " " . $lines[$i + 1];
                $i++;
            }

            $merged[] = $line;
        }

        return $merged;
    }

    private static function getMethodAnnotations(string $className, string $methodName): array
    {
        $annotations = self::getMethod($className, $methodName)->getDocComment();
        return self::getAnnotationLines($annotations);
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


    private static function extractFormatData(array $annotations)
    {
        $filtered = self::filterAnnotations($annotations, "@format");
        // there should either be one or none format declaration
        if (count($filtered) == 0) {
            return null;
        }
        if (count($filtered) > 1) {
            ///TODO: throw exception
            echo "Error in extractFormatData: Multiple format definitions.\n";
            return null;
        }

        # sample: @format uuid
        $annotation = $filtered[0];
        $tokens = explode(" ", $annotation);
        $format = $tokens[1];
        
        return $format;
    }

    /**
     * Checks all @checked_param annotations of a method and returns a map from parameter names to their formats.
     * @param string $className The name of the containing class.
     * @param string $methodName The name of the method.
     * @return array
     */
    public static function extractMethodCheckedParams(string $className, string $methodName): array
    {
        $annotations = self::getMethodAnnotations($className, $methodName);
        $filtered = self::filterAnnotations($annotations, "@checked_param");
        
        $formatPrefix = "format:";

        $paramMap = [];
        foreach ($filtered as $annotation) {
            // sample: @checked_param format:group group
            $tokens = explode(" ", $annotation);
            $format = substr($tokens[1], strlen($formatPrefix));
            $name = $tokens[2];
            $paramMap[$name] = $format;
        }

        return $paramMap;
    }

    /**
     * Parses the field annotations of a class and returns their metadata.
     * @param string $className The name of the class.
     * @return array{format: string|null, type: string|null} with the field name as the key.
     */

    public static function getClassFormats(string $className)
    {
        $class = new ReflectionClass($className);
        $fields = get_class_vars($className);
        $formats = [];
        foreach ($fields as $fieldName => $value) {
            $field = $class->getProperty($fieldName);
            $format = self::extractFormatData(self::getAnnotationLines($field->getDocComment()));
            # get null if there is no type
            $fieldType = $field->getType()?->getName();

            $formats[$fieldName] = [
                "type" => $fieldType,
                "format" => $format,
            ];
        }

        return $formats;
    }

    /**
     * Creates a mapping from formats to class names, where the class defines the format.
     */
    public static function getFormatDefinitions()
    {
        ///TODO: this should be more sophisticated
        $classes = get_declared_classes();

        // maps format names to class names
        $formatClassMap = [];

        foreach ($classes as $className) {
            $class = new ReflectionClass($className);
            $annotations = self::getAnnotationLines($class->getDocComment());
            $type_defs = self::filterAnnotations($annotations, "@format_def");
            if (count($type_defs) !== 1) {
                continue;
            }

            $tokens = explode(" ", $type_defs[0]);

            // the second token is the group name, the first one is the tag
            $formatClassMap[$tokens[1]] = $className;
        }

        return $formatClassMap;
    }

    /**
     * Extracts all primitive validator methods (starting with "validate") and returns a map from format to a callback.
     * The callbacks have one parameter that is passed to the validator.
     */
    private static function getPrimitiveValidators(): array
    {
            $instance = new PrimitiveFormatValidators();
            $className = get_class($instance);
            $methodNames = get_class_methods($className);

            $validators = [];
        foreach ($methodNames as $methodName) {
            // all validation methods start with validate
            if (!str_starts_with($methodName, "validate")) {
                continue;
            }

            $annotations = self::getMethodAnnotations($className, $methodName);
            $format = self::extractFormatData($annotations);
            $callback = function ($param) use ($instance, $methodName) {
                return $instance->$methodName($param);
            };
            $validators[$format] = $callback;
        }

            return $validators;
    }

    private static function getMetaValidators(): array
    {
        return [];
    }

    private static function getValidators(): array
    {
        return array_merge(self::getPrimitiveValidators(), self::getMetaValidators());
    }
}
