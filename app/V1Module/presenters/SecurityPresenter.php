<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\InvalidArgumentException;
use Exception;
use Nette\Application\IPresenterFactory;
use Nette\Routing\Router;
use Nette\Application\UI\Presenter;
use Nette\Http;

class SecurityPresenter extends BasePresenter
{
    /**
     * @var IPresenterFactory
     * @inject
     */
    public $presenterFactory;

    /**
     * @var Router
     * @inject
     */
    public $router;

    /**
     * @POST
     */
    #[Post("url", new VString(), "URL of the resource that we are checking", required: true)]
    #[Post("method", new VString(), "The HTTP method", required: true)]
    public function actionCheck()
    {
        $requestParams = $this->router->match(
            new Http\Request(
                new Http\UrlScript("https://foo.tld/" . ltrim($this->getRequest()->getPost("url"), "/"), "/"),
                [],
                [],
                [],
                [],
                $this->getRequest()->getPost("method")
            )
        );

        if (!$requestParams) {
            throw new InvalidArgumentException("url");
        }

        $presenterName = $requestParams["presenter"] ?? null;
        if (!$presenterName) {
            throw new InvalidArgumentException("url");
        }

        $presenter = $this->presenterFactory->createPresenter($presenterName);
        if (!($presenter instanceof BasePresenter)) {
            $this->checkFailed();
            return;
        }

        $action = $requestParams["action"] ?? Presenter::DEFAULT_ACTION;
        $methodName = $presenter->formatPermissionCheckMethod($action);
        if (!method_exists($presenter, $methodName)) {
            $this->checkFailed();
            return;
        }

        $presenterReflection = $presenter->getReflection();
        $arguments = $presenterReflection->combineArgs(
            $presenterReflection->getMethod($methodName),
            $requestParams
        );
        $result = true;

        try {
            call_user_func_array([$presenter, $methodName], $arguments);
        } catch (Exception $e) {
            $result = false;
        }

        $this->sendSuccessResponse(
            [
                "result" => $result,
                "isResultReliable" => true
            ]
        );
    }

    protected function checkFailed()
    {
        $this->sendSuccessResponse(
            [
                "result" => true,
                "isResultReliable" => false
            ]
        );
    }
}
