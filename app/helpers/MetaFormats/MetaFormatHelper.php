<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\Attributes\FFile;
use App\Helpers\MetaFormats\Attributes\File;
use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\Attributes\FormatParameterAttribute;
use App\Helpers\MetaFormats\Attributes\FPath;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Attributes\FQuery;
use App\Helpers\MetaFormats\Attributes\Param;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use ReflectionClass;
use App\Helpers\Swagger\AnnotationHelper;
use ReflectionMethod;
use ReflectionProperty;

class MetaFormatHelper
{
    private static string $formatDefinitionFolder = __DIR__ . '/FormatDefinitions';
    private static string $formatDefinitionsNamespace = "App\\Helpers\\MetaFormats\\FormatDefinitions";

    /**
     * Checks whether an entity contains a Format attribute and extracts the format if so.
     * @param \ReflectionClass|\ReflectionProperty|\ReflectionMethod $reflectionObject A reflection
     * object of the entity.
     * @return ?string Returns the format or null if no Format attribute was present.
     */
    public static function extractFormatFromAttribute(
        ReflectionClass | ReflectionProperty | ReflectionMethod $reflectionObject
    ): ?string {
        $formatAttributes = $reflectionObject->getAttributes(Format::class);
        if (count($formatAttributes) === 0) {
            return null;
        }

        $formatAttribute = $formatAttributes[0]->newInstance();
        return $formatAttribute->class;
    }

    /**
     * Extracts all endpoint parameter attributes.
     * @param \ReflectionMethod $reflectionMethod The endpoint reflection method.
     * @return array Returns an array of parameter attributes.
     */
    public static function getEndpointAttributes(ReflectionMethod $reflectionMethod): array
    {
        $path = $reflectionMethod->getAttributes(name: Path::class);
        $query = $reflectionMethod->getAttributes(name: Query::class);
        $post = $reflectionMethod->getAttributes(name: Post::class);
        $file = $reflectionMethod->getAttributes(name: File::class);
        $param = $reflectionMethod->getAttributes(name: Param::class);
        return array_merge($path, $query, $post, $file, $param);
    }

    /**
     * Fetches all attributes of a method and extracts the parameter data.
     * @param \ReflectionMethod $reflectionMethod The method reflection object.
     * @return array Returns an array of RequestParamData objects with the extracted data.
     */
    public static function extractRequestParamData(ReflectionMethod $reflectionMethod): array
    {
        $attrs = self::getEndpointAttributes($reflectionMethod);
        $data = [];
        foreach ($attrs as $attr) {
            $paramAttr = $attr->newInstance();
            $data[] = new RequestParamData(
                $paramAttr->type,
                $paramAttr->paramName,
                $paramAttr->description,
                $paramAttr->required,
                $paramAttr->validators,
                $paramAttr->nullable,
            );
        }

        return $data;
    }

    /**
     * Finds the format attribute of the property and extracts its data.
     * @param \ReflectionProperty $reflectionObject The reflection object of the property.
     * @throws \App\Exceptions\InternalServerException Thrown when there is not exactly one format attribute.
     * @return RequestParamData Returns the data from the attribute.
     */
    public static function extractFormatParameterData(ReflectionProperty $reflectionObject): RequestParamData
    {
        // find all property attributes
        $longAttributes = $reflectionObject->getAttributes(FormatParameterAttribute::class);
        $pathAttributes = $reflectionObject->getAttributes(FPath::class);
        $queryAttributes = $reflectionObject->getAttributes(FQuery::class);
        $postAttributes = $reflectionObject->getAttributes(FPost::class);
        $fileAttributes = $reflectionObject->getAttributes(FFile::class);
        $requestAttributes = array_merge(
            $longAttributes,
            $pathAttributes,
            $queryAttributes,
            $postAttributes,
            $fileAttributes
        );

        // there should be only one attribute
        if (count($requestAttributes) == 0) {
            throw new InternalServerException(
                "The field {$reflectionObject->name} of "
                    . "class {$reflectionObject->class} does not have a property attribute."
            );
        }
        if (count($requestAttributes) > 1) {
            throw new InternalServerException(
                "The field {$reflectionObject->name} of "
                    . "class {$reflectionObject->class} has more than one attribute."
            );
        }

        $requestAttribute = $requestAttributes[0]->newInstance();
        return new RequestParamData(
            $requestAttribute->type,
            $reflectionObject->name,
            $requestAttribute->description,
            $requestAttribute->required,
            $requestAttribute->validators,
            $requestAttribute->nullable,
        );
    }

    /**
     * Debug method used to extract all attribute data of a reflection object.
     * @param \ReflectionClass|\ReflectionProperty|\ReflectionMethod $reflectionObject The reflection object.
     * @return array Returns an array, where each element represents an attribute in top-down order of definition
     *   in the code. Each element is an instance of the specific attribute.
     */
    public static function debugGetAttributes(
        ReflectionClass | ReflectionProperty | ReflectionMethod $reflectionObject
    ): array {
        $requestAttributes = $reflectionObject->getAttributes();
        $data = [];
        foreach ($requestAttributes as $attr) {
            $data[] = $attr->newInstance();
        }
        return $data;
    }

    /**
     * Parses the format attributes of class fields and returns their metadata.
     * @param string $className The name of the class.
     * @return array Returns a dictionary with the field name as the key and RequestParamData as the value.
     */
    public static function createNameToFieldDefinitionsMap(string $className): array
    {
        $class = new ReflectionClass(objectOrClass: $className);
        $fields = get_class_vars($className);
        $formats = [];
        foreach ($fields as $fieldName => $value) {
            $field = $class->getProperty($fieldName);
            $requestParamData = self::extractFormatParameterData($field);
            $formats[$fieldName] = $requestParamData;
        }

        return $formats;
    }

    /**
     * Finds all defined formats and returns an array of their names.
     */
    public static function createFormatNamesArray()
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

        // formats are just class names
        return array_values($classes);
    }

    /**
     * Creates a MetaFormat instance of the given format.
     * @param string $format The name of the format.
     * @throws \App\Exceptions\InternalServerException Thrown when the format does not exist.
     * @return \App\Helpers\MetaFormats\MetaFormat Returns the constructed MetaFormat instance.
     */
    public static function createFormatInstance(string $format): MetaFormat
    {
        if (!FormatCache::formatExists($format)) {
            throw new InternalServerException("The format $format does not exist.");
        }

        $instance = new $format();
        return $instance;
    }
}
