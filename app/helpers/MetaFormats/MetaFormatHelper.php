<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\Attributes\FormatAttribute;
use App\Helpers\MetaFormats\Attributes\FormatParameterAttribute;
use App\Helpers\MetaFormats\Attributes\ParamAttribute;
use App\Helpers\MetaFormats\Attributes\RequestParamAttribute;
use ReflectionClass;
use App\Helpers\Swagger\AnnotationHelper;
use ReflectionMethod;
use ReflectionProperty;

class MetaFormatHelper
{
    private static string $formatDefinitionFolder = __DIR__ . '/FormatDefinitions';
    private static string $formatDefinitionsNamespace = "App\\Helpers\\MetaFormats\\FormatDefinitions";

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
     * Checks whether an entity contains a FormatAttribute and extracts the format if so.
     * @param \ReflectionClass|\ReflectionProperty|\ReflectionMethod $reflectionObject A reflection
     * object of the entity.
     * @throws \App\Exceptions\InternalServerException Thrown when the FormatAttribute was used incorrectly.
     * @return ?string Returns the format or null if no FormatAttribute was present.
     */
    public static function extractFormatFromAttribute(
        ReflectionClass|ReflectionProperty|ReflectionMethod $reflectionObject
    ): ?string {
        $formatAttributes = $reflectionObject->getAttributes(FormatAttribute::class);
        if (count($formatAttributes) === 0) {
            return null;
        }

        // check attribute correctness
        $formatArguments = $formatAttributes[0]->getArguments();
        if (count($formatArguments) !== 1) {
            $name = $reflectionObject->getName();
            throw new InternalServerException("The entity $name does not have a single attribute argument.");
        }

        return $formatArguments[0];
    }

    /**
     * Fetches all attributes of a method and extracts the parameter data.
     * @param \ReflectionMethod $reflectionMethod The method reflection object.
     * @return array Returns an array of RequestParamData objects with the extracted data.
     */
    public static function extractRequestParamData(ReflectionMethod $reflectionMethod): array
    {
        $attrs = $reflectionMethod->getAttributes(RequestParamAttribute::class);
        $data = [];
        foreach ($attrs as $attr) {
            $paramAttr = $attr->newInstance();
            $data[] = new RequestParamData(
                $paramAttr->type,
                $paramAttr->paramName,
                $paramAttr->description,
                $paramAttr->required,
                $paramAttr->validators
            );
        }

        return $data;
    }

    public static function extractRequestAttributeData(
        ReflectionClass|ReflectionProperty|ReflectionMethod $reflectionObject
    ): ?RequestParamData {
        $requestAttribute = $reflectionObject->getAttributes(FormatParameterAttribute::class);
        if (count($requestAttribute) === 0) {
            return null;
        }

        $requestArguments = $requestAttribute[0]->getArguments();
        $type = $requestArguments["type"];
        $description = array_key_exists("description", $requestArguments) ? $requestArguments["description"] : "";
        $required = array_key_exists("required", $requestArguments) ? $requestArguments["required"] : true;

        return new RequestParamData($type, "TODO_IMPLEMENT_FOR_FormatParameterAttribute", $description, $required);
    }

    /**
     * Debug method used to extract all attribute data of a reflection object.
     * @param \ReflectionClass|\ReflectionProperty|\ReflectionMethod $reflectionObject The reflection object.
     * @return array Returns an array, where each element represents an attribute in top-down order of definition
     *   in the code. Each element is an array of constructor arguments of the attribute.
     */
    public static function debugGetAttributes(
        ReflectionClass|ReflectionProperty|ReflectionMethod $reflectionObject
    ): array {
        $requestAttributes = $reflectionObject->getAttributes();
        $data = [];
        foreach ($requestAttributes as $attr) {
            $data[] = $attr->getArguments();
        }
        return $data;
    }

  /**
   * Parses the format attributes of class fields and returns their metadata.
   * @param string $className The name of the class.
   * @return array{format: string|null, type: string|null} with the field name as the key.
   */
    public static function createNameToFieldDefinitionsMap(string $className)
    {
        $class = new ReflectionClass($className);
        $fields = get_class_vars($className);
        $formats = [];
        foreach ($fields as $fieldName => $value) {
            $field = $class->getProperty($fieldName);
            // the format can be null (not present)
            $format = self::extractFormatFromAttribute($field);
            // get null if there is no type
            $reflectionType = $field->getType();
            $fieldType = $reflectionType?->getName();
            $nullable = $reflectionType?->allowsNull() ?? false;

            $requestParamData = self::extractRequestAttributeData($field);
            if ($requestParamData === null) {
                throw new InternalServerException(
                    "The field $fieldName of class $className does not have a RequestAttribute."
                );
            }

            $formats[$fieldName] = new FieldFormatDefinition($format, $fieldType, $nullable, $requestParamData);
        }

        return $formats;
    }

  /**
   * Creates a mapping from formats to class names, where the class defines the format.
   */
    public static function createFormatToClassMap()
    {
        // scan directory of format definitions
        $formatFiles = scandir(self::$formatDefinitionFolder);
        // filter out only format files ending with 'Format.php'
        $formatFiles = array_filter($formatFiles, function ($file) {
            return str_ends_with($file, "Format.php");
        });
        $classes = array_map(function (string $file) {
            $fileWithoutExtension = substr($file, 0, -4);
            return self::$formatDefinitionsNamespace . "\\$fileWithoutExtension";
        }, $formatFiles);

        // maps format names to class names
        $formatClassMap = [];

        foreach ($classes as $className) {
            // get the format attribute
            $class = new ReflectionClass($className);
            $format = self::extractFormatFromAttribute($class);
            if ($format === null) {
                throw new InternalServerException("The class {$className} does not have the format attribute.");
            }

            $formatClassMap[$format] = $className;
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

    /**
     * Creates a MetaFormat instance of the given format.
     * @param string $format The name of the format.
     * @throws \App\Exceptions\InternalServerException Thrown when the format does not exist.
     * @return \App\Helpers\MetaFormats\MetaFormat Returns the constructed MetaFormat instance.
     */
    public static function createFormatInstance(string $format): MetaFormat
    {
        $formatToClassMap = FormatCache::getFormatToClassMap();
        if (!array_key_exists($format, $formatToClassMap)) {
            throw new InternalServerException("The format $format does not exist.");
        }

        $className = $formatToClassMap[$format];
        $instance = new $className();
        return $instance;
    }
}
