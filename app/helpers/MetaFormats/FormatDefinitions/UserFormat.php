<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\FormatAttribute;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FormatParameterAttribute;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VString;

#[FormatAttribute(UserFormat::class)]
class UserFormat extends MetaFormat
{
    #[FormatAttribute("email")]
    #[FormatParameterAttribute(Type::Post, new VEmail(), "An email that will serve as a login name")]
    public string $email;

    #[FormatParameterAttribute(Type::Post, new VString(), "First name")]
    public string $firstName;

    #[FormatParameterAttribute(Type::Post, new VString(), "Last name")]
    public string $lastName;

    #[FormatParameterAttribute(Type::Post, new VString(), "A password for authentication")]
    public string $password;

    #[FormatParameterAttribute(Type::Post, new VString(), "A password confirmation")]
    public string $passwordConfirm;

    #[FormatParameterAttribute(Type::Post, new VString(), "Identifier of the instance to register in")]
    public string $instanceId;

    #[FormatParameterAttribute(
        Type::Post,
        new VString(),
        "Titles that are placed before user name",
        required: false,
        nullable: true
    )]
    public ?string $titlesBeforeName;

    #[FormatParameterAttribute(
        Type::Post,
        new VString(),
        "Titles that are placed after user name",
        required: false,
        nullable: true
    )]
    public ?string $titlesAfterName;
}
