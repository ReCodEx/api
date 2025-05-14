<?php

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidApiArgumentException;
use App\Helpers\MetaFormats\Attributes\FPath;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Attributes\FQuery;
use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\FormatDefinitions\UserFormat;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\MetaFormats\Validators\BaseValidator;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VObject;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use Tester\Assert;

$container = require_once __DIR__ . "/../bootstrap.php";

#[Format(RequiredNullabilityTestFormat::class)]
class RequiredNullabilityTestFormat extends MetaFormat
{
    #[FPost(new VInt(), required: true, nullable: false)]
    public ?int $requiredNotNullable;

    #[FPost(new VInt(), required: false, nullable: false)]
    public ?int $notRequiredNotNullable;

    #[FPost(new VInt(), required: true, nullable: true)]
    public ?int $requiredNullable;

    #[FPost(new VInt(), required: false, nullable: true)]
    public ?int $notRequiredNullable;
}

#[Format(ValidationTestFormat::class)]
class ValidationTestFormat extends MetaFormat
{
    #[FPost(new VInt(), required: true, nullable: false)]
    public ?int $post;

    #[FPath(new VInt(), required: true, nullable: false)]
    public ?int $path;

    #[FQuery(new VInt(), required: true, nullable: false)]
    public ?int $query;

    #[FQuery(new VInt(), required: false)]
    public ?int $queryOptional;

    public function validateStructure()
    {
        return $this->query == 1;
    }
}

/**
 * @testCase
 */
class TestFormats extends Tester\TestCase
{
    /** @var  Nette\DI\Container */
    protected $container;

    public function __construct()
    {
        global $container;
        $this->container = $container;
    }

    private function injectFormat(string $format)
    {
        // initialize the cache
        FormatCache::getFormatToFieldDefinitionsMap();
        FormatCache::getFormatNamesHashSet();

        // inject the format name
        $hashSetReflector = new ReflectionProperty(FormatCache::class, "formatNamesHashSet");
        $hashSetReflector->setAccessible(true);
        $formatNamesHashSet = $hashSetReflector->getValue();
        $formatNamesHashSet[$format] = true;
        $hashSetReflector->setValue(null, $formatNamesHashSet);

        // inject the format definitions
        $formatMapReflector = new ReflectionProperty(FormatCache::class, "formatToFieldFormatsMap");
        $formatMapReflector->setAccessible(true);
        $formatToFieldFormatsMap = $formatMapReflector->getValue();
        $formatToFieldFormatsMap[$format] = MetaFormatHelper::createNameToFieldDefinitionsMap($format);
        $formatMapReflector->setValue(null, $formatToFieldFormatsMap);

        Assert::notNull(FormatCache::getFieldDefinitions($format), "Tests whether a format was injected successfully.");
    }

    public function testInvalidFieldName()
    {
        self::injectFormat(RequiredNullabilityTestFormat::class);

        Assert::throws(
            function () {
                try {
                    $format = new RequiredNullabilityTestFormat();
                    $format->checkedAssign("invalidIdentifier", null);
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            InternalServerException::class
        );
    }

    public function testRequiredNotNullable()
    {
        self::injectFormat(RequiredNullabilityTestFormat::class);
        $fieldName = "requiredNotNullable";

        // it is not nullable so this has to throw
        Assert::throws(
            function () use ($fieldName) {
                try {
                    $format = new RequiredNullabilityTestFormat();
                    $format->checkedAssign($fieldName, null);
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            InvalidApiArgumentException::class
        );

        // assign 1
        $format = new RequiredNullabilityTestFormat();
        $format->checkedAssign($fieldName, 1);
        Assert::equal($format->$fieldName, 1);
    }

    public function testNullAssign()
    {
        self::injectFormat(RequiredNullabilityTestFormat::class);
        $format = new RequiredNullabilityTestFormat();

        // not required and not nullable fields can contain null (not required overrides not nullable)
        foreach (["requiredNullable", "notRequiredNullable", "notRequiredNotNullable"] as $fieldName) {
            // assign 1
            $format->checkedAssign($fieldName, 1);
            Assert::equal($format->$fieldName, 1);

            // assign null
            $format->checkedAssign($fieldName, null);
            Assert::equal($format->$fieldName, null);
        }
    }

    public function testIndividualParamValidation()
    {
        self::injectFormat(ValidationTestFormat::class);
        $format = new ValidationTestFormat();

        // path and query parameters do not have strict validation
        $format->checkedAssign("query", "1");
        $format->checkedAssign("query", 1);
        $format->checkedAssign("path", "1");
        $format->checkedAssign("path", 1);

        // post parameters have strict validation, assigning a string will throw
        $format->checkedAssign("post", 1);
        Assert::throws(
            function () use ($format) {
                try {
                    $format->checkedAssign("post", "1");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            InvalidApiArgumentException::class
        );

        // null cannot be assigned unless the parameter is nullable or not required
        $format->checkedAssign("queryOptional", null);
        Assert::throws(
            function () use ($format) {
                try {
                    $format->checkedAssign("query", null);
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            InvalidApiArgumentException::class
        );
    }

    public function testAggregateParamValidation()
    {
        self::injectFormat(ValidationTestFormat::class);
        $format = new ValidationTestFormat();

        $format->checkedAssign("query", 1);
        $format->checkedAssign("path", 1);
        $format->checkedAssign("post", 1);
        $format->checkedAssign("queryOptional", null);
        $format->validate();

        // invalidate a format field
        Assert::throws(
            function () use ($format) {
                try {
                    // bypass the checkedAssign
                    $format->path = null;
                    $format->validate();
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            InvalidApiArgumentException::class
        );

        // assign valid values to all fields, but fail the structural constraint of $query == 1
        $format->checkedAssign("path", 1);
        $format->checkedAssign("query", 2);
        Assert::throws(
            function () use ($format) {
                try {
                    $format->validate();
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            BadRequestException::class
        );
    }

    ///TODO: nested formats, loose format
}

(new TestFormats())->run();
