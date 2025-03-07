<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidArgumentException;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\Swagger\AnnotationParameterData;
use Exception;

/**
 * Data class containing metadata for request parameters.
 */
class RequestParamData
{
    public Type $type;
    public string $name;
    public string $description;
    public bool $required;
    public array $validators;
    public bool $nullable;

    public function __construct(
        Type $type,
        string $name,
        string $description,
        bool $required,
        array $validators,
        bool $nullable = false,
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->description = $description;
        $this->required = $required;
        $this->validators = $validators;
        $this->nullable = $nullable;
    }

    /**
     * Checks whether a value meets this definition. If the definition is not met, an exception is thrown.
     * The method has no return value.
     * @param mixed $value The value to be checked.
     * @throws \App\Exceptions\InvalidArgumentException Thrown when the value does not meet the definition.
     */
    public function conformsToDefinition(mixed $value)
    {
        // check if null
        if ($value === null) {
            // optional parameters can be null
            if (!$this->required) {
                return;
            }

            // required parameters can be null only if explicitly nullable
            if (!$this->nullable) {
                throw new InvalidArgumentException(
                    $this->name,
                    "The parameter is not nullable and thus cannot be null."
                );
            }

            // only non null values should be validated
            // (validators do not expect null)
            return;
        }

        // use every provided validator
        foreach ($this->validators as $validator) {
            if (!$validator->validate($value)) {
                $type = $validator::SWAGGER_TYPE;
                throw new InvalidArgumentException(
                    $this->name,
                    "The provided value did not pass the validation of type '{$type}'."
                );
            }
        }
    }

    private function hasValidators(): bool
    {
        if (is_array($this->validators)) {
            return count($this->validators) > 0;
        }
        return $this->validators !== null;
    }

    /**
     * Converts the metadata into metadata used for swagger generation.
     * @throws \App\Exceptions\InternalServerException Thrown when the parameter metadata is corrupted.
     * @return AnnotationParameterData Return metadata used for swagger generation.
     */
    public function toAnnotationParameterData()
    {
        if (!$this->hasValidators()) {
            throw new InternalServerException(
                "No validator found for parameter {$this->name}, description: {$this->description}."
            );
        }

        // determine swagger type
        $nestedArraySwaggerType = null;
        $swaggerType = $this->validators[0]::SWAGGER_TYPE;
        // extract array element type
        if ($this->validators[0] instanceof VArray) {
            $nestedArraySwaggerType = $this->validators[0]->getElementSwaggerType();
        }

        // retrieve the example value from the getExampleValue method if present
        $exampleValue = null;
        if (method_exists(get_class($this->validators[0]), "getExampleValue")) {
            $exampleValue = $this->validators[0]->getExampleValue();
        }

        return new AnnotationParameterData(
            $swaggerType,
            $this->name,
            $this->description,
            strtolower($this->type->name),
            $this->required,
            $this->nullable,
            $exampleValue,
            $nestedArraySwaggerType,
        );
    }
}
