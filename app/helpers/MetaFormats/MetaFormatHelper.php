<?php

namespace App\Helpers\MetaFormats;

use ReflectionClass;
use App\Helpers\Swagger\AnnotationHelper;

class MetaFormatHelper
{
    private static function extractFormatData(array $annotations)
    {
        $filtered = AnnotationHelper::filterAnnotations($annotations, "@format");
        // there should either be one or none format declaration
        if (count($filtered) == 0) {
            return null;
        }
        if (count($filtered) > 1) {
            ///TODO: throw exception
            echo "Error in extractFormatData: Multiple format definitions.\n";
            return null;
        }

        // sample: @format uuid
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
        $annotations = AnnotationHelper::getMethodAnnotations($className, $methodName);
        $filtered = AnnotationHelper::filterAnnotations($annotations, "@checked_param");

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
            $format = self::extractFormatData(AnnotationHelper::getAnnotationLines($field->getDocComment()));
            // get null if there is no type
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
            $annotations = AnnotationHelper::getAnnotationLines($class->getDocComment());
            $type_defs = AnnotationHelper::filterAnnotations($annotations, "@format_def");
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

            $annotations = AnnotationHelper::getMethodAnnotations($className, $methodName);
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

    public static function getValidators(): array
    {
        return array_merge(self::getPrimitiveValidators(), self::getMetaValidators());
    }
}
