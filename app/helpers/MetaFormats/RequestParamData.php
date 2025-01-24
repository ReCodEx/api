<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InvalidArgumentException;
use App\Helpers\MetaFormats\Validators\ArrayValidator;
use App\Helpers\MetaFormats\Validators\StringValidator;
use App\Helpers\Swagger\AnnotationParameterData;

class RequestParamData
{
    public RequestParamType $type;
    public string $name;
    public string $description;
    public bool $required;
    public array $validators;
    public bool $nullable;

    public function __construct(
        RequestParamType $type,
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

        // use every provided validator
        foreach ($this->validators as $validator) {
            if (!$validator->validate($value)) {
                throw new InvalidArgumentException($this->name);
            }
        }

        return true;
    }

    private function hasValidators(): bool
    {
        return count($this->validators) > 0;
    }

    public function toAnnotationParameterData()
    {
        $swaggerType = "string";
        $nestedArraySwaggerType = null;
        if ($this->hasValidators()) {
            $swaggerType = $this->validators[0]::SWAGGER_TYPE;
            if ($this->validators[0] instanceof ArrayValidator) {
                $nestedArraySwaggerType = $this->validators[0]->getElementSwaggerType();
            }
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
