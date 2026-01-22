<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Exceptions\InvalidApiArgumentException;
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
     * A preflight test whether given URL (and HTTP method) would be allowed by internal ACL checks
     * (for the current user).
     * @POST
     */
    #[Post("url", new VMixed(), "URL of the resource that we are checking", required: true, nullable: true)]
    #[Post("method", new VMixed(), "The HTTP method", required: true, nullable: true)]
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
            throw new InvalidApiArgumentException("url");
        }

        $presenterName = $requestParams["presenter"] ?? null;
        if (!$presenterName) {
            throw new InvalidApiArgumentException("url");
        }

        $presenter = $this->presenterFactory->createPresenter($presenterName);
        if (!($presenter instanceof BasePresenter)) {
            $this->checkFailed();
            return;
        }

        $action = $requestParams["action"] ?? Presenter::DefaultAction;
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
