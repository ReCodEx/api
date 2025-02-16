<?php

namespace App\Helpers\MetaFormats\AnnotationConversion;

use App\Exceptions\InternalServerException;

class Utils
{
    public static function shortenClass(string $className)
    {
        $tokens = explode("\\", $className);
        return end($tokens);
    }

    public static function checkValidationNullability(string $validation): bool
    {
        return str_ends_with($validation, "|null");
    }

    public static function fileStringToLines(string $fileContent): array
    {
        $lines = preg_split("/((\r?\n)|(\r\n?))/", $fileContent);
        if ($lines == false) {
            throw new InternalServerException("File content cannot be split into lines");
        }
        return $lines;
    }

    public static function linesToFileString(array $lines): string
    {
        return implode("\n", $lines);
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
}
