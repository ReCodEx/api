<?php

namespace App\Helpers\MetaFormats;

use App\Helpers\Swagger\AnnotationHelper;

class MetaFormat
{
    // validates primitive formats of intrinsic PHP types
    ///TODO: make this static somehow (or cached)
    private $validators;

    public function __construct()
    {
        $this->validators = MetaFormatHelper::getValidators();
    }


    /**
     * Validates the given format.
     * @return bool Returns whether the format and all nested formats are valid.
     */
    public function validate()
    {
        // check whether all higher level contracts hold
        if (!$this->validateSelf()) {
            return false;
        }

        // check properties
        $selfFormat = MetaFormatHelper::getClassFormats(get_class($this));
        foreach ($selfFormat as $propertyName => $propertyFormat) {
            ///TODO: check if this is true
            /// if the property is checked by type only, there is no need to check it as an invalid assignment
            /// would rise an error
            $value = $this->$propertyName;
            $format = $propertyFormat["format"];
            if ($format === null) {
                continue;
            }

            // enables parsing more complicated formats (string[]?, string?[], string?[][]?, ...)
            $parsedFormat = new FormatParser($format);
            if (!$this->recursiveFormatChecker($value, $parsedFormat)) {
                return false;
            }
        }

        return true;
    }

    private function recursiveFormatChecker($value, FormatParser $parsedFormat)
    {
        // enables parsing more complicated formats (string[]?, string?[], string?[][]?, ...)

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
                if (!$this->recursiveFormatChecker($element, $parsedFormat->nested)) {
                    return false;
                }
            }
            return true;
        }

        ///TODO: raise an error
        // check whether the validator exists
        if (!array_key_exists($parsedFormat->format, $this->validators)) {
            echo "Error: missing validator for format: " . $parsedFormat->format . "\n";
            return false;
        }

        return $this->validators[$parsedFormat->format]($value);
    }

    /**
     * Validates this format. Automatically called by the validate method on all fields.
     * Primitive formats should always override this, composite formats might want to override
     * this in case more complex contracts need to be enforced.
     * This method should not check the format of nested types.
     * @return bool Returns whether the format is valid.
     */
    protected function validateSelf()
    {
        // there are no constraints by default
        return true;
    }
}
