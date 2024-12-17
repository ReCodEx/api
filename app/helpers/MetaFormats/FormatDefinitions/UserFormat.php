<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\FormatAttribute;
use App\Helpers\MetaFormats\MetaFormat;

#[FormatAttribute("userRegistration")]
class UserFormat extends MetaFormat
{
    //#[FormatAttribute("email")]
    public string $email;
    public string $firstName;
    public string $lastName;
    public string $password;
    public string $passwordConfirm;
    public string $instanceId;
    public ?string $titlesBeforeName;
    public ?string $titlesAfterName;
}
