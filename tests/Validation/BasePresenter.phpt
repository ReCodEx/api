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

/**
 * A Format class used to test FPost, FPath, FQuery and structure validation.
 */
#[Format(ValidationTestFormat::class)]
class PresenterTestFormat extends MetaFormat
{
    #[FPost(new VInt())]
    public ?int $post;

    #[FPath(new VInt())]
    public ?int $path;

    #[FQuery(new VInt())]
    public ?int $query;

    // The following properties will not be set in the tests, they are here to check that optional parameters
    // can be omitted from the request.
    #[FPost(new VInt(), required: false)]
    public ?int $postOptional;

    #[FQuery(new VInt(), required: false)]
    public ?int $queryOptional;

    /**
     * This class requires the query property to be 1.
     */
    public function validateStructure()
    {
        return $this->query == 1;
    }
}

/**
 * A Presenter used to test loose attributes, Format attributes, and a combination of both.
 */
class TestPresenter extends BasePresenter
{
    #[Post("post", new VInt())]
    #[Query("query", new VInt())]
    #[Path("path", new VInt())]
    // The following parameters will not be set in the tests, they are here to check that optional parameters
    // can be omitted from the request.
    #[Post("postOptional", new VInt(), required: false)]
    #[Query("queryOptional", new VInt(), required: false)]
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
 * This test suite simulates a BasePresenter receiving user requests.
 * The tests start by creating a presenter object, defining request data, and running the request.
 * The tests include scenarios with both valid and invalid request data.
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

    /**
     * Injects a Format class to the FormatCache and checks whether it was injected successfully.
     * @param string $format The Format class name.
     */
    private static function injectFormatChecked(string $format)
    {
        MockHelper::injectFormat($format);
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

        // set an invalid parameter value and assert that the validation fails ("path" should be an int)
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

    public function testLooseMissing()
    {
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testLoose", "path" => "1", "query" => "1"],
            post: [] // missing path parameter
        );

        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            BadRequestException::class
        );
    }

    public function testFormatValid()
    {
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a valid request object
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testFormat", "path" => "2", "query" => "1"],
            post: ["post" => 3]
        );

        $response = $presenter->run($request);
        Assert::equal("OK", $response->getPayload()["payload"]);

        // the presenter should automatically create a valid format object
        /** @var PresenterTestFormat */
        $format = $presenter->getFormatInstance();
        Assert::notNull($format);

        // throws when invalid
        $format->validate();

        // check if the values match
        Assert::equal($format->path, 2);
        Assert::equal($format->query, 1);
        Assert::equal($format->post, 3);
    }

    public function testFormatInvalidParameter()
    {
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with invalid parameters ("path" should be an int)
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

    public function testFormatMissingParameter()
    {
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testFormat", "query" => "1"], // missing path
            post: ["post" => 3]
        );

        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            BadRequestException::class
        );
    }

    public function testFormatInvalidStructure()
    {
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with invalid structure ("query" has to be 1)
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
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a valid request object
        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testCombined", "path" => "2", "query" => "1"],
            post: ["post" => 3, "loose" => 4]
        );
        $response = $presenter->run($request);
        Assert::equal("OK", $response->getPayload()["payload"]);

        // the presenter should automatically create a valid format object
        /** @var PresenterTestFormat */
        $format = $presenter->getFormatInstance();
        Assert::notNull($format);

        // throws when invalid
        $format->validate();

        // check if the values match
        Assert::equal($format->path, 2);
        Assert::equal($format->query, 1);
        Assert::equal($format->post, 3);
    }

    public function testCombinedInvalidFormatParameters()
    {
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with invalid parameters ("path" should be an int)
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
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with invalid structure ("query" has to be 1)
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
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        // create a request object with an invalid loose parameter (it should be an int)
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

    public function testCombinedMissingLooseParam()
    {
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testCombined", "path" => "1", "query" => "1"],
            post: ["post" => 1] // missing loose parameter
        );

        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            BadRequestException::class
        );
    }

    public function testCombinedMissingFormatParam()
    {
        self::injectFormatChecked(PresenterTestFormat::class);
        $presenter = new TestPresenter();
        MockHelper::initPresenter($presenter);

        $request = new Request(
            "name",
            method: "POST",
            params: ["action" => "testCombined", "path" => "1", "query" => "1"],
            post: ["loose" => 1] // missing post parameter
        );

        Assert::throws(
            function () use ($presenter, $request) {
                $presenter->run($request);
            },
            BadRequestException::class
        );
    }
}

(new TestBasePresenter())->run();
