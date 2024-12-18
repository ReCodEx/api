<?php

namespace App\Helpers\MetaFormats;

class RequestParamData
{
    public RequestParamType $type;
    public string $description;
    public bool $required;

    public function __construct(RequestParamType $type, string $description, bool $required)
    {
        $this->type = $type;
        $this->description = $description;
        $this->required = $required;
    }
}
