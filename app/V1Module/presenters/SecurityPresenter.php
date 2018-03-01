<?php
namespace App\V1Module;

use App\Exceptions\InvalidArgumentException;
use App\V1Module\Presenters\BasePresenter;
use Exception;
use Nette\Application\IPresenterFactory;
use Nette\Application\IRouter;
use Nette\Application\UI\Presenter;
use Nette\Http;

class SecurityPresenter extends BasePresenter {
  /**
   * @var IPresenterFactory
   * @inject
   */
  public $presenterFactory;

  /**
   * @var IRouter
   * @inject
   */
  public $router;

  /**
   * @POST
   * @Param(name="url", type="post", required=true, description="URL of the resource that we are checking")
   * @Param(name="method", type="post", required=true, description="The HTTP method")
   */
  public function actionCheck() {
    $appRequest = $this->router->match(new Http\Request(
      new Http\UrlScript("https://foo.tld/" . ltrim($this->getRequest()->getPost("url"), "/"), "/"),
      null, null, null, null, null,
      $this->getRequest()->getPost("method")
    ));

    if (!$appRequest) {
      throw new InvalidArgumentException("url");
    }

    $presenter = $this->presenterFactory->createPresenter($appRequest->getPresenterName());
    if (!($presenter instanceof BasePresenter)) {
      $this->checkFailed();
      return;
    }

    $action = $appRequest->getParameter("action");
    if (empty($action)) {
      $action = Presenter::DEFAULT_ACTION;
    }
    $methodName = $presenter->formatPermissionCheckMethod($action);
    if (!method_exists($presenter, $methodName)) {
      $this->checkFailed();
      return;
    }

    $presenterReflection = $presenter->getReflection();
    $arguments = $presenterReflection->combineArgs(
      $presenterReflection->getMethod($methodName),
      $appRequest->getParameters()
    );
    $result = true;

    try {
      call_user_func_array([$presenter, $methodName], $arguments);
    } catch (Exception $e) {
      $result = false;
    }

    $this->sendSuccessResponse([
      "result" => $result,
      "certain" => true
    ]);
  }

  protected function checkFailed() {
    $this->sendSuccessResponse([
      "result" => true,
      "certain" => false
    ]);
  }
}