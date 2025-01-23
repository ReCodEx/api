<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;

class FormatCache
{
    private static ?array $formatToClassMap = null;
    private static ?array $classToFormatMap = null;
    private static ?array $formatToFieldFormatsMap = null;
    private static ?array $validators = null;

    public static function getFormatToClassMap(): array
    {
        if (self::$formatToClassMap == null) {
            self::$formatToClassMap = MetaFormatHelper::createFormatToClassMap();
        }
        return self::$formatToClassMap;
    }

    public static function getClassToFormatMap(): array
    {
        if (self::$classToFormatMap == null) {
            self::$classToFormatMap = [];
            $formatToClassMap = self::getFormatToClassMap();
            foreach ($formatToClassMap as $format => $class) {
                self::$classToFormatMap[$class] = $format;
            }
        }
        return self::$classToFormatMap;
    }

    public static function getFormatToFieldDefinitionsMap(): array
    {
        if (self::$formatToFieldFormatsMap == null) {
            self::$formatToFieldFormatsMap = [];
            $formatToClassMap = self::getFormatToClassMap();
            foreach ($formatToClassMap as $format => $class) {
                self::$formatToFieldFormatsMap[$format] = MetaFormatHelper::createNameToFieldDefinitionsMap($class);
            }
        }
        return self::$formatToFieldFormatsMap;
    }

    public static function getFormatFieldNames(string $format): array
    {
        $formatToFieldDefinitionsMap = self::getFormatToFieldDefinitionsMap();
        if (!array_key_exists($format, $formatToFieldDefinitionsMap)) {
            throw new InternalServerException("The format $format does not have a field format definition.");
        }
        return array_keys($formatToFieldDefinitionsMap[$format]);
    }

    public static function getFieldDefinitions(string $className)
    {
        $classToFormatMap = self::getClassToFormatMap();
        if (!array_key_exists($className, $classToFormatMap)) {
            throw new InternalServerException("The class $className does not have a format definition.");
        }

        $format = $classToFormatMap[$className];
        $formatToFieldFormatsMap = self::getFormatToFieldDefinitionsMap();
        if (!array_key_exists($format, $formatToFieldFormatsMap)) {
            throw new InternalServerException("The format $format does not have a field format definition.");
        }

        return $formatToFieldFormatsMap[$format];
    }
}
