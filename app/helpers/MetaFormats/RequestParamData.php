<?php

namespace App\Helpers\MetaFormats;

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

    public function toAnnotationParameterData()
    {
        $dataType = null;
        if (count($this->validators) > 0) {
            $dataType = $this->validators[0]::SWAGGER_TYPE;
        }

        ///TODO: does not pass null
        return new AnnotationParameterData(
            $dataType,
            $this->name,
            $this->description,
            strtolower($this->type->name)
        );
    }
}
