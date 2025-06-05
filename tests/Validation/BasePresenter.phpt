<?php

use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidApiArgumentException;
use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\Attributes\FPath;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Attributes\FQuery;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\Mocks\MockHelper;
use App\V1Module\Presenters\BasePresenter;
use Nette\Application\Request;
use Tester\Assert;

$container = require_once __DIR__ . "/../bootstrap.php";

#[Format(ValidationTestFormat::class)]
class PresenterTestFormat extends MetaFormat
{
    #[FPost(new VInt())]
    public ?int $post;

    #[FPath(new VInt())]
    public ?int $path;

    #[FQuery(new VInt())]
    public ?int $query;

    public function validateStructure()
    {
        return $this->query == 1;
    }
}

class TestPresenter extends BasePresenter
{
    #[Post("post", new VInt())]
    #[Query("query", new VInt())]
    #[Path("path", new VInt())]
    public function actionTestLoose()
    {
        $this->sendSuccessResponse("OK");
    }

    #[Format(PresenterTestFormat::class)]
    public function actionTestFormat()
    {
        $this->sendSuccessResponse("OK");
    }

    #[Format(PresenterTestFormat::class)]
    #[Post("loose", new VInt())]
    public function actionTestCombined()
    {
        $this->sendSuccessResponse("OK");
    }
}

/**
 * @testCase
 */
class TestBasePresenter extends Tester\TestCase
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

    public function testLooseValid()
    {
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testLoose", "path" => "1", "query" => "1"],
            post: ["post" => 1]
        );

        $response = $presenter->run($request);
        Assert::equal("OK", $response->getPayload()["payload"]);
    }

    public function testLooseInvalid()
    {
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // set an invalid parameter value and assert that the validation fails
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testLoose", "path" => "string", "query" => "1"],
            post: ["post" => 1]
        );
        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            InvalidApiArgumentException::class
        );
    }

    public function testFormatValid()
    {
        self::injectFormat(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a valid request object
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testFormat", "path" => "1", "query" => "1"],
            post: ["post" => 1]
        );

        $response = $presenter->run($request);
        Assert::equal("OK", $response->getPayload()["payload"]);
        
        // the presenter should automatically create a valid format object
        /** @var PresenterTestFormat */
        $format = $presenter->getFormatInstance();
        Assert::notNull($format);
        $format->validate();

        // check if the values match
        Assert::equal($format->path, 1);
        Assert::equal($format->query, 1);
        Assert::equal($format->post, 1);
    }

    public function testFormatInvalidField()
    {
        self::injectFormat(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with invalid fields
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testFormat", "path" => "string", "query" => "1"],
            post: ["post" => 1]
        );
        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            InvalidApiArgumentException::class
        );
    }

    public function testFormatInvalidStructure()
    {
        self::injectFormat(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with invalid structure
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testFormat", "path" => "1", "query" => "0"],
            post: ["post" => 1]
        );
        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            BadRequestException::class
        );
    }

    public function testCombinedValid()
    {
        self::injectFormat(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a valid request object
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testCombined", "path" => "1", "query" => "1"],
            post: ["post" => 1, "loose" => 1]
        );
        $response = $presenter->run($request);
        Assert::equal("OK", $response->getPayload()["payload"]);

        // the presenter should automatically create a valid format object
        /** @var PresenterTestFormat */
        $format = $presenter->getFormatInstance();
        Assert::notNull($format);
        $format->validate();

        // check if the values match
        Assert::equal($format->path, 1);
        Assert::equal($format->query, 1);
        Assert::equal($format->post, 1);
    }

    public function testCombinedInvalidFormatFields()
    {
        self::injectFormat(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with invalid fields
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testCombined", "path" => "string", "query" => "1"],
            post: ["post" => 1, "loose" => 1]
        );
        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            InvalidApiArgumentException::class
        );
    }

    public function testCombinedInvalidStructure()
    {
        self::injectFormat(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with invalid structure
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testCombined", "path" => "1", "query" => "0"],
            post: ["post" => 1, "loose" => 1]
        );
        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            BadRequestException::class
        );
    }

    public function testCombinedInvalidLooseParam()
    {
        self::injectFormat(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with an invalid loose parameter
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testCombined", "path" => "1", "query" => "1"],
            post: ["post" => 1, "loose" => "string"]
        );
        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            InvalidApiArgumentException::class
        );
    }
}

(new TestBasePresenter())->run();
