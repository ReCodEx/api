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

#[Format(Test5BodyFormat::class)]
class Test5BodyFormat extends MetaFormat
{
    #[FPost(new VString())]
    public string $a;

    #[FPost(new VString())]
    public string $b;

    #[FPost(new VString())]
    public string $c;

    #[FPost(new VString())]
    public string $d;

    #[FPost(new VString())]
    public string $e;
}
