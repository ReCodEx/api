<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\FormatAttribute;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FormatParameterAttribute;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VString;

#[FormatAttribute(UserFormat::class)]
class UserFormat extends MetaFormat
{
    #[FormatAttribute("email")]
    #[FormatParameterAttribute(type: Type::Post, description: "An email that will serve as a login name")]
    public string $email;

    #[FormatParameterAttribute(type: Type::Post, description: "First name")]
    public string $firstName;

    #[FormatParameterAttribute(type: Type::Post, description: "Last name", validators: [ new VString(2) ])]
    public string $lastName;

    #[FormatParameterAttribute(type: Type::Post, description: "A password for authentication")]
    public string $password;

    #[FormatParameterAttribute(type: Type::Post, description: "A password confirmation")]
    public string $passwordConfirm;

    #[FormatParameterAttribute(type: Type::Post, description: "Identifier of the instance to register in")]
    public string $instanceId;

    #[FormatParameterAttribute(
        type: Type::Post,
        description: "Titles that are placed before user name",
        required: false,
        nullable: true
    )]
    public ?string $titlesBeforeName;

    #[FormatParameterAttribute(
        type: Type::Post,
        description: "Titles that are placed after user name",
        required: false,
        nullable: true
    )]
    public ?string $titlesAfterName;
}
