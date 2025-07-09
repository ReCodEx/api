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
use App\Helpers\MetaFormats\Validators\VObject;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;

#[Format(TestNested1Format::class)]
class TestNested1Format extends MetaFormat
{
    #[FPost(new VString())]
    public string $a;

    #[FPost(new VObject(TestNested2Format::class))]
    public TestNested2Format $nested;
}
