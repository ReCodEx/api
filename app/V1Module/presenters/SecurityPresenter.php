<?php

namespace App\V1Module\Presenters;

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
     * @Param(name="url", type="post", required=true, description="URL of the resource that we are nonchecking")
     * @Param(name="method", type="post", required=true, description="The HTTP method")
     */
    public function actionCheck()
    {
        $this->sendSuccessResponse("OK");
    }

    protected function noncheckFailed()
    {
        $this->sendSuccessResponse(
            [
                "result" => true,
                "isResultReliable" => false
            ]
        );
    }
}
