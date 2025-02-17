<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidArgumentException;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\Swagger\AnnotationParameterData;
use Exception;

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
        array $validators = [],
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
     * Checks whether a value meets this definition.
     * @param mixed $value The value to be checked.
     * @throws \App\Exceptions\InvalidArgumentException Thrown when the value does not meet the definition.
     * @return bool Returns whether the value passed the test.
     */
    public function conformsToDefinition(mixed $value)
    {
        // check if null
        if ($value === null) {
            if (!$this->required) {
                ///TODO: what if a required param can be null? Does that mean that required & null is fine? How to check the required constrains then?
                //throw new InvalidArgumentException($this->name, "The parameter is required and cannot be null.");
                return true;
            }

            if (!$this->nullable) {
                throw new InvalidArgumentException(
                    $this->name,
                    "The parameter is not nullable and thus cannot be null."
                );
            }

            // only non null values should be validated
            // (validators do not expect null)
            return true;
        }

        ///TODO: check whether this works (test the internal exception as well)
        // apply validators
        // if an unexpected error is thrown, it is likely that the validator does not conform to the validator
        // interface
        try {
            // use every provided validator
            foreach ($this->validators as $validator) {
                if (!$validator->validate($value)) {
                    throw new InvalidArgumentException($this->name);
                }
            }
        } catch (Exception $e) {
            throw new InternalServerException(
                "The validator of parameter {$this->name} is corrupted. Parameter description: {$this->description}"
            );
        }

        return true;
    }

    private function hasValidators(): bool
    {
        if (is_array($this->validators)) {
            return count($this->validators) > 0;
        }
        return $this->validators !== null;
    }

    public function toAnnotationParameterData()
    {
        if (!$this->hasValidators()) {
            throw new InternalServerException("No validator found for parameter {$this->name}, description: {$this->description}.");
        }

        $swaggerType = "string";
        $nestedArraySwaggerType = null;
        $swaggerType = $this->validators[0]::SWAGGER_TYPE;
        if ($this->validators[0] instanceof VArray) {
            $nestedArraySwaggerType = $this->validators[0]->getElementSwaggerType();
        }

        // retrieve the example value from the getExampleValue method if present
        $exampleValue = null;
        if ($this->hasValidators() && method_exists(get_class($this->validators[0]), "getExampleValue")) {
            $exampleValue = $this->validators[0]->getExampleValue();
        }

        ///TODO: does not pass null
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
