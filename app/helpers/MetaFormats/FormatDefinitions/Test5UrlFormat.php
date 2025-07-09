<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FPath;
use App\Helpers\MetaFormats\Attributes\FQuery;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;

#[Format(Test5UrlFormat::class)]
class Test5UrlFormat extends MetaFormat
{
    #[FPath(new VString())]
    public string $a;

    #[FPath(new VString())]
    public string $b;

    #[FQuery(new VString())]
    public ?string $c;

    #[FQuery(new VString())]
    public ?string $d;

    #[FQuery(new VString())]
    public ?string $e;
}
