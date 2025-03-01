<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;

/**
 * Cache for various format related data.
 * Acts as a singleton storage because all of the cached data is static.
 */
class FormatCache
{
    // do not access the following three arrays directly, use the getter methods instead
    // (there is no guarantee that the arrays are initialized)
    private static ?array $formatNames = null;
    private static ?array $formatNamesHashSet = null;
    private static ?array $formatToFieldFormatsMap = null;

    /**
     * @return array Returns a dictionary of dictionaries: [<formatName> => [<fieldName> => RequestParamData, ...], ...]
     * mapping formats to their fields and field metadata.
     */
    public static function getFormatToFieldDefinitionsMap(): array
    {
        if (self::$formatToFieldFormatsMap == null) {
            self::$formatToFieldFormatsMap = [];
            $formatNames = self::getFormatNames();
            foreach ($formatNames as $format) {
                self::$formatToFieldFormatsMap[$format] = MetaFormatHelper::createNameToFieldDefinitionsMap($format);
            }
        }
        return self::$formatToFieldFormatsMap;
    }

    /**
     * @return array Returns an array of all defined formats.
     */
    public static function getFormatNames(): array
    {
        if (self::$formatNames == null) {
            self::$formatNames = MetaFormatHelper::createFormatNamesArray();
        }
        return self::$formatNames;
    }

    /**
     * @return array Returns a hash set of all defined formats (actually a dictionary with arbitrary values).
     */
    public static function getFormatNamesHashSet(): array
    {
        if (self::$formatNamesHashSet == null) {
            $formatNames = self::getFormatNames();
            self::$formatNamesHashSet = [];
            foreach ($formatNames as $formatName) {
                self::$formatNamesHashSet[$formatName] = true;
            }
        }
        return self::$formatNamesHashSet;
    }

    public static function formatExists(string $format): bool
    {
        return array_key_exists($format, self::getFormatNamesHashSet());
    }

    /**
     * Fetches field metadata for the given format.
     * @param string $format The name of the format.
     * @throws \App\Exceptions\InternalServerException Thrown when the format is corrupted.
     * @return array Returns a dictionary of field names to RequestParamData.
     */
    public static function getFieldDefinitions(string $format)
    {
        if (!self::formatExists($format)) {
            throw new InternalServerException("The class $format does not have a format definition.");
        }

        $formatToFieldFormatsMap = self::getFormatToFieldDefinitionsMap();
        if (!array_key_exists($format, $formatToFieldFormatsMap)) {
            throw new InternalServerException("The format $format does not have a field format definition.");
        }

        return $formatToFieldFormatsMap[$format];
    }
}
