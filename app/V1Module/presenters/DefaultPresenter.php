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
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Helpers\ApiConfig;

class DefaultPresenter extends BasePresenter
{

    /**
     * @var ApiConfig
     * @inject
     */
    public $apiConfig;

    /**
     * @GET
     * @throws \Nette\Application\AbortException
     */
    public function actionDefault()
    {
        $this->sendJson(
            [
                "project" => $this->apiConfig->getName(),
                "version" => $this->apiConfig->getVersion(),
                "website" => $this->apiConfig->getAddress()
            ]
        );
    }
}
