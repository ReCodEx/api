<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FormatParameterAttribute;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VString;

/**
 * Format definition used by the RegistrationPresenter::actionCreateInvitation endpoint.
 */
#[Format(UserFormat::class)]
class UserFormat extends MetaFormat
{
    #[FPost(new VEmail(), "An email that will serve as a login name")]
    public ?string $email;

    #[FPost(new VString(2), "First name", required: true)]
    public string $firstName;

    #[FPost(new VString(2), "Last name")]
    public ?string $lastName;

    #[FPost(new VString(1), "Identifier of the instance to register in")]
    public ?string $instanceId;

    #[FPost(new VString(1), "Titles that are placed before user name", required: false)]
    public ?string $titlesBeforeName;

    #[FPost(new VString(1), "Titles that are placed after user name", required: false)]
    public ?string $titlesAfterName;

    #[FPost(new VArray(), "List of group IDs in which the user is added right after registration", required: false)]
    public ?array $groups;

    #[FPost(new VString(2, 2), "Language used in the invitation email (en by default).", required: false)]
    public ?string $locale;
}
