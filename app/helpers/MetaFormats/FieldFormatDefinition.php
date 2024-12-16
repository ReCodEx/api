<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;

class FieldFormatDefinition
{
    public ?string $format;
    // A string name of the field type yielded by 'ReflectionProperty::getType()'.
    public ?string $type;

    ///TODO: double check this
    private static array $gettypeToReflectiveMap = [
        "boolean" => "bool",
        "integer" => "int",
        "double" => "double",
        "string" => "string",
        "array" => "array",
        "object" => "object",
        "resource" => "resource",
        "NULL" => "null",
    ];

    /**
     * Constructs a field format definition.
     * Either the @format or @type parameter need to have a non-null value (or both).
     * @param ?string $format The format of the field.
     * @param ?string $type The PHP type of the field yielded by a 'ReflectionProperty::getType()' call.
     * @throws \App\Exceptions\InternalServerException Thrown when both @format and @type were null.
     */
    public function __construct(?string $format, ?string $type)
    {
        // if both are null, there is no way to validate an assigned value
        if ($format === null && $type === null) {
            throw new InternalServerException("Both the format and type of a field definition were undefined.");
        }

        $this->format = $format;
        $this->type = $type;
    }

    /**
     * Checks whether a value meets this definition.
     * @param mixed $value The value to be checked.
     * @throws \App\Exceptions\InternalServerException Thrown when the format does not have a validator.
     * @return bool Returns whether the value passed the test.
     */
    public function conformsToDefinition(mixed $value)
    {
        // use format validators if possible
        if ($this->format !== null) {
            // enables parsing more complicated formats (string[]?, string?[], string?[][]?, ...)
            $parsedFormat = new FormatParser($this->format);
            return self::recursiveFormatChecker($value, $parsedFormat);
        }

        // convert the gettype return value to the reflective return value
        $valueType = gettype($value);
        if (!array_key_exists($valueType, self::$gettypeToReflectiveMap)) {
            throw new InternalServerException("Unknown gettype value: $valueType");
        }
        return $valueType === $this->type;
    }

    /**
     * Checks whether the value fits a format recursively.
     * The format can contain array modifiers and thus all array elements need to be checked recursively.
     * @param mixed $value The value to be checked
     * @param \App\Helpers\MetaFormats\FormatParser $parsedFormat A parsed format used for recursive traversal.
     * @throws \App\Exceptions\InternalServerException Thrown when a format does not have a validator.
     * @return bool Returns whether the value conforms to the format.
     */
    private static function recursiveFormatChecker(mixed $value, FormatParser $parsedFormat): bool
    {
        // check nullability
        if ($value === null) {
            return $parsedFormat->nullable;
        }

        // handle arrays
        if ($parsedFormat->isArray) {
            if (!is_array($value)) {
                return false;
            }

            // if any element fails, the whole format fails
            foreach ($value as $element) {
                if (!self::recursiveFormatChecker($element, $parsedFormat->nested)) {
                    return false;
                }
            }
            return true;
        }

        // check whether the validator exists
        $validators = FormatCache::getValidators();
        if (!array_key_exists($parsedFormat->format, $validators)) {
            throw new InternalServerException("The format {$parsedFormat->format} does not have a validator.");
        }

        return $validators[$parsedFormat->format]($value);
    }
}
