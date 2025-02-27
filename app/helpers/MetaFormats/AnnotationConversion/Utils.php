<?php

namespace App\Helpers\MetaFormats\AnnotationConversion;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\V1Module\Presenters\BasePresenter;

class Utils
{
    // links all @Param location types to corresponding attribute classes.
    private static array $paramLocationToAttributeClassDictionary = [
        "post" => Post::class,
        "query" => Query::class,
        "path" => Path::class,
    ];

    /**
     * Converts a fully qualified class name to a class name without namespace prefixes.
     * @param string $className Fully qualified class name, such
     * as "App\Helpers\MetaFormats\AnnotationConversion\Utils".
     * @return string Class name without namespace prefixes, such as "Utils".
     */
    public static function shortenClass(string $className)
    {
        $tokens = explode("\\", $className);
        return end($tokens);
    }

    /**
     * Checks whether the validation string ends with the "|null" suffix.
     * Validation strings contain the "null" qualifier always at the end of the string.
     * @param string $validation The validation string.
     * @return bool Returns whether the validation ends with "|null".
     */
    public static function checkValidationNullability(string $validation): bool
    {
        return str_ends_with($validation, "|null");
    }

    /**
     * Splits a string into lines.
     * @param string $fileContent The string to be split.
     * @throws \App\Exceptions\InternalServerException Thrown when the string cannot be split.
     * @return array The lines of the string.
     */
    public static function fileStringToLines(string $fileContent): array
    {
        $lines = preg_split("/((\r?\n)|(\r\n?))/", $fileContent);
        if ($lines == false) {
            throw new InternalServerException("File content cannot be split into lines");
        }
        return $lines;
    }

    /**
     * @return string[] Returns an array of Validator class names (without the namespace).
     */
    public static function getValidatorNames()
    {
        $dir = __DIR__ . "/../Validators";
        $baseFilenames = scandir($dir);
        $classNames = [];
        foreach ($baseFilenames as $filename) {
            if (!str_ends_with($filename, ".php")) {
                continue;
            }

            // remove the ".php" suffix
            $className = substr($filename, 0, -4);
            $classNames[] = $className;
        }
        return $classNames;
    }

    public static function getPresenterNamespace()
    {
        // extract presenter namespace from BasePresenter
        $namespaceTokens = explode("\\", BasePresenter::class);
        $namespace = implode("\\", array_slice($namespaceTokens, 0, count($namespaceTokens) - 1));
        return $namespace;
    }

    /**
     * Returns the attribute class name (without namespace) matching the input parameter location string.
     * @param string $type The location type of the parameter (path, query, post).
     * @throws \App\Exceptions\InternalServerException Thrown when an unexpected type is provided.
     * @return string Returns the attribute class name matching the parameter location type.
     */
    public static function getAttributeClassFromString(string $type)
    {
        if (!array_key_exists($type, self::$paramLocationToAttributeClassDictionary)) {
            throw new InternalServerException("Unsupported parameter location: $type");
        }

        $className = self::$paramLocationToAttributeClassDictionary[$type];
        return self::shortenClass($className);
    }

    public static function getParamAttributeClassNames()
    {
        return [
            self::shortenClass(Post::class),
            self::shortenClass(Query::class),
            self::shortenClass(Path::class),
        ];
    }
}
