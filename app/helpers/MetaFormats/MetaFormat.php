<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;
use App\Helpers\Swagger\AnnotationHelper;

use function Symfony\Component\String\b;

class MetaFormat
{
    /**
     * Checks whether the value can be assigned to a field.
     * @param string $fieldName The name of the field.
     * @param mixed $value The value to be assigned.
     * @throws \App\Exceptions\InternalServerException Thrown when the field was not found.
     * @return bool Returns whether the value conforms to the type and format of the field.
     */
    public function checkIfAssignable(string $fieldName, mixed $value): bool
    {
        $fieldFormats = FormatCache::getFieldDefinitions(get_class($this));
        if (!array_key_exists($fieldName, $fieldFormats)) {
            throw new InternalServerException("The field name $fieldName is not present in the format definition.");
        }
        // get the definition for the specific field
        $formatDefinition = $fieldFormats[$fieldName];
        return $formatDefinition->conformsToDefinition($value);
    }

    /**
     * Tries to assign a value to a field. If the value does not conform to the field format, it will not be assigned.
     * @param string $fieldName The name of the field.
     * @param mixed $value The value to be assigned.
     * @return bool Returns whether the value was assigned.
     */
    public function checkedAssign(string $fieldName, mixed $value)
    {
        if (!$this->checkIfAssignable($fieldName, $value)) {
            return false;
        }

        $this->$fieldName = $value;
        return true;
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

        // go through all fields and check whether they were assigned properly
        $fieldFormats = FormatCache::getFieldDefinitions(get_class($this));
        foreach ($fieldFormats as $fieldName => $fieldFormat) {
            if (!$this->checkIfAssignable($fieldName, $this->$fieldName)) {
                return false;
            }
        }

        return true;
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
