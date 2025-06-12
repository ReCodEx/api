<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidApiArgumentException;

class MetaFormat
{
    /**
     * Checks whether the value can be assigned to a field. If not, an exception is thrown.
     * The method has no return value.
     * @param string $fieldName The name of the field.
     * @param mixed $value The value to be assigned.
     * @throws InternalServerException Thrown when the field was not found.
     * @throws InvalidApiArgumentException Thrown when the value is not assignable.
     */
    public function checkIfAssignable(string $fieldName, mixed $value)
    {
        $fieldFormats = FormatCache::getFieldDefinitions(get_class($this));
        if (!array_key_exists($fieldName, $fieldFormats)) {
            throw new InternalServerException("The field name $fieldName is not present in the format definition.");
        }
        // get the definition for the specific field
        $formatDefinition = $fieldFormats[$fieldName];
        $formatDefinition->conformsToDefinition($value);
    }

    /**
     * Tries to assign a value to a field. If the value does not conform to the field format, an exception is thrown.
     *  The exception details why the value does not conform to the format.
     * @param string $fieldName The name of the field.
     * @param mixed $value The value to be assigned.
     * @throws InternalServerException Thrown when the field was not found.
     * @throws InvalidApiArgumentException Thrown when the value is not assignable.
     */
    public function checkedAssign(string $fieldName, mixed $value)
    {
        $this->checkIfAssignable($fieldName, $value);
        $this->$fieldName = $value;
    }

    /**
     * Validates the given format.
     * @throws InvalidApiArgumentException Thrown when a value is not assignable.
     * @throws BadRequestException Thrown when the structural constraints were not met.
     */
    public function validate()
    {
        // check whether all higher level contracts hold
        if (!$this->validateStructure()) {
            throw new BadRequestException("The structural constraints of the format were not met.");
        }

        // go through all fields and check whether they were assigned properly
        $fieldFormats = FormatCache::getFieldDefinitions(get_class($this));
        foreach ($fieldFormats as $fieldName => $fieldFormat) {
            $this->checkIfAssignable($fieldName, $this->$fieldName);

            // check nested formats recursively
            if ($this->$fieldName instanceof MetaFormat) {
                $this->$fieldName->validate();
            }
        }
    }

    /**
     * Validates this format. Automatically called by the validate method on all suitable fields.
     * Formats might want to override this in case more complex contracts need to be enforced.
     * This method should not check the format of nested types.
     * @return bool Returns whether the format is valid.
     */
    public function validateStructure()
    {
        // there are no constraints by default
        return true;
    }
}
