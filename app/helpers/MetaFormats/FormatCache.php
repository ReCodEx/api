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

    // this array caches loose attribute data which are added over time by the presenters
    private static array $actionToRequestParamDataMap = [];

    // array that caches Format attribute format strings for actions
    private static array $actionToFormatMap = [];

    /**
     * @param string $actionPath The presenter class name joined with the name of the action method.
     * @return bool Returns whether the loose parameters of the action are cached.
     */
    public static function looseParametersCached(string $actionPath): bool
    {
        return array_key_exists($actionPath, self::$actionToRequestParamDataMap);
    }

    /**
     * @param string $actionPath The presenter class name joined with the name of the action method.
     * @return bool Returns whether the action Format attribute string was cached.
     */
    public static function formatAttributeStringCached(string $actionPath): bool
    {
        return array_key_exists($actionPath, self::$actionToFormatMap);
    }

    /**
     * @param string $actionPath The presenter class name joined with the name of the action method.
     * @param string|null $format The attribute format string or null if there is none.
     */
    public static function cacheFormatAttributeString(string $actionPath, string | null $format)
    {
        self::$actionToFormatMap[$actionPath] = $format;
    }

    /**
     * @param string $actionPath The presenter class name joined with the name of the action method.
     * @return string|null Returns action Format attribute string or null if there is no Format attribute.
     */
    public static function getFormatAttributeString(string $actionPath): string | null
    {
        return self::$actionToFormatMap[$actionPath];
    }

    /**
     * @param string $actionPath The presenter class name joined with the name of the action method.
     * @return array Returns the cached RequestParamData array of the loose attributes.
     */
    public static function getLooseParameters(string $actionPath): array
    {
        return self::$actionToRequestParamDataMap[$actionPath];
    }

    /**
     * Caches a RequestParamData array from the loose attributes of an action.
     * @param string $actionPath The presenter class name joined with the name of the action method.
     * @param array $data The RequestParamData array to be cached.
     */
    public static function cacheLooseParameters(string $actionPath, array $data): void
    {
        self::$actionToRequestParamDataMap[$actionPath] = $data;
    }

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
