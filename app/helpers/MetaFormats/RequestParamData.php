<?php

namespace App\Helpers\MetaFormats;

class RequestParamData
{
    public RequestParamType $type;
    public string $description;
    public bool $required;
    public array $validators;

    public function __construct(RequestParamType $type, string $description, bool $required, array $validators = [])
    {
        $this->type = $type;
        $this->description = $description;
        $this->required = $required;
        $this->validators = $validators;
    }
}
