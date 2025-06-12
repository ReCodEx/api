<?php

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidApiArgumentException;
use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\Attributes\FPath;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Attributes\FQuery;
use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VObject;
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

#[Format(ParentFormat::class)]
class ParentFormat extends MetaFormat
{
    #[FQuery(new VInt(), required: true, nullable: false)]
    public ?int $field;

    #[FPost(new VObject(NestedFormat::class), required: true, nullable: false)]
    public NestedFormat $nested;

    public function validateStructure()
    {
        return $this->field == 1;
    }
}

#[Format(NestedFormat::class)]
class NestedFormat extends MetaFormat
{
    #[FQuery(new VInt(), required: true, nullable: false)]
    public ?int $field;

    public function validateStructure()
    {
        return $this->field == 2;
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

    private static function injectFormat(string $format)
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

    public function testIndividualParamValidationPermissive()
    {
        self::injectFormat(ValidationTestFormat::class);
        $format = new ValidationTestFormat();

        // path and query parameters do not have strict validation
        $format->checkedAssign("query", "1");
        $format->checkedAssign("query", 1);
        $format->checkedAssign("path", "1");
        $format->checkedAssign("path", 1);

        // make sure that the above assignments did not throw
        Assert::true(true);
    }

    public function testIndividualParamValidationStrict()
    {
        self::injectFormat(ValidationTestFormat::class);
        $format = new ValidationTestFormat();

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
    }

    public function testIndividualParamValidationNullable()
    {
        self::injectFormat(ValidationTestFormat::class);
        $format = new ValidationTestFormat();

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

        // assign valid values and validate
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
        Assert::false($format->validateStructure());
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

    public function testNestedFormat()
    {
        self::injectFormat(NestedFormat::class);
        self::injectFormat(ParentFormat::class);
        $nested = new NestedFormat();
        $parent = new ParentFormat();

        // assign valid values that do not pass structural validation
        // (both fields need to be 1 to pass)
        $nested->checkedAssign("field", 0);
        $parent->checkedAssign("field", 0);
        $parent->checkedAssign("nested", $nested);

        Assert::false($nested->validateStructure());
        Assert::false($parent->validateStructure());

        // invalid structure should throw during validation
        Assert::throws(
            function () use ($nested) {
                $nested->validate();
            },
            BadRequestException::class
        );
        Assert::throws(
            function () use ($parent) {
                $parent->validate();
            },
            BadRequestException::class
        );

        // fix the structural constain in the parent
        $parent->checkedAssign("field", 1);
        Assert::true($parent->validateStructure());

        // make sure that the structural error in the nested format propagates to the parent
        Assert::throws(
            function () use ($parent) {
                $parent->validate();
            },
            BadRequestException::class
        );
    }
}

(new TestFormats())->run();
