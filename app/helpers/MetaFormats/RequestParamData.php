<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidApiArgumentException;
use App\Helpers\MetaFormats\Validators\BaseValidator;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VFile;
use App\Helpers\MetaFormats\Validators\VObject;
use App\Helpers\Swagger\AnnotationParameterData;

/**
 * Data class containing metadata for request parameters.
 */
class RequestParamData
{
    public Type $type;
    public string $name;
    public string $description;
    public bool $required;
    /**
     * @var BaseValidator[]
     */
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
     * @param ?array $validators If set, these validators will be used instead of the ones defined for the parameter.
     * @throws InvalidApiArgumentException Thrown when the value does not meet the definition.
     */
    public function conformsToDefinition(mixed $value, ?array $validators = null)
    {
        // use custom validators if provided
        if ($validators == null) {
            $validators = $this->validators;
        }

        // check if null
        if ($value === null) {
            // optional parameters can be null
            if (!$this->required) {
                return;
            }

            // required parameters can be null only if explicitly nullable
            if (!$this->nullable) {
                throw new InvalidApiArgumentException(
                    $this->name,
                    "The parameter is not nullable and thus cannot be null."
                );
            }

            // only non null values should be validated
            // (validators do not expect null)
            return;
        }

        // use every provided validator
        foreach ($validators as $validator) {
            if (!$validator->validate($value)) {
                $type = $validator::SWAGGER_TYPE;
                throw new InvalidApiArgumentException(
                    $this->name,
                    "The provided value did not pass the validation of type '{$type}'."
                );
            }
        }
    }

    /**
     * Returns the format name if the parameter should be interpreted as a format and not as a primitive type.
     * @return ?string Returns the format name or null if the param represents a primitive type.
     */
    public function getFormatName(): ?string
    {
        // all format params have to have a VObject validator
        foreach ($this->validators as $validator) {
            if ($validator instanceof VObject) {
                return $validator->format;
            }
        }

        // return null for primitive types
        return null;
    }

    /**
     * Converts the metadata into metadata used for swagger generation.
     * @throws \App\Exceptions\InternalServerException Thrown when the parameter metadata is corrupted.
     * @return AnnotationParameterData Return metadata used for swagger generation.
     */
    public function toAnnotationParameterData()
    {
        if (count($this->validators) === 0) {
            throw new InternalServerException(
                "No validator found for parameter {$this->name}, description: {$this->description}."
            );
        }

        // determine swagger type
        $nestedArraySwaggerType = null;
        $arrayDepth = null;
        $swaggerType = $this->validators[0]::SWAGGER_TYPE;
        // extract array depth and element type
        if ($this->validators[0] instanceof VArray) {
            $arrayDepth = $this->validators[0]->getArrayDepth();
            $nestedArraySwaggerType = $this->validators[0]->getElementSwaggerType();
        }

        // get example value from the first validator
        $exampleValue = $this->validators[0]->getExampleValue();

        // get constraints from validators
        $constraints = null;
        foreach ($this->validators as $validator) {
            $constraints = $validator->getConstraints();
            // it is assumed that at most one validator defines constraints
            if ($constraints !== null) {
                break;
            }
        }

        // add nested parameter data if this is an object
        $format = $this->getFormatName();
        $nestedObjectParameterData = null;
        if ($format !== null) {
            $nestedRequestParmData = FormatCache::getFieldDefinitions($format);
            $nestedObjectParameterData = array_map(function (RequestParamData $data) {
                return $data->toAnnotationParameterData();
            }, $nestedRequestParmData);
        }

        // get file request type if file
        $fileRequestType = null;
        if ($this->validators[0] instanceof VFile) {
            $fileRequestType = $this->validators[0]->fileRequestType;
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
            $arrayDepth,
            $nestedObjectParameterData,
            $constraints,
            $fileRequestType,
        );
    }
}
