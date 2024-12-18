<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\FormatAttribute;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\RequestAttribute;
use App\Helpers\MetaFormats\RequestParamType;

#[FormatAttribute("userRegistration")]
class UserFormat extends MetaFormat
{
    #[FormatAttribute("email")]
    #[RequestAttribute(type: RequestParamType::Post, description: "An email that will serve as a login name")]
    public string $email;

    #[RequestAttribute(type: RequestParamType::Post, description: "First name")]
    public string $firstName;

    #[RequestAttribute(type: RequestParamType::Post, description: "Last name")]
    public string $lastName;

    #[RequestAttribute(type: RequestParamType::Post, description: "A password for authentication")]
    public string $password;

    #[RequestAttribute(type: RequestParamType::Post, description: "A password confirmation")]
    public string $passwordConfirm;

    #[RequestAttribute(type: RequestParamType::Post, description: "Identifier of the instance to register in")]
    public string $instanceId;

    #[RequestAttribute(
        type: RequestParamType::Post,
        description: "Titles that are placed before user name",
        required: false
    )]
    public ?string $titlesBeforeName;

    #[RequestAttribute(
        type: RequestParamType::Post,
        description: "Titles that are placed after user name",
        required: false
    )]
    public ?string $titlesAfterName;
}
