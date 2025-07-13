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
     * @POST
     */
    #[Post("url", new VMixed(), "URL of the resource that we are checking", required: true, nullable: true)]
    #[Post("method", new VMixed(), "The HTTP method", required: true, nullable: true)]
    public function actionCheck()
    {
        $this->sendSuccessResponse("OK");
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
