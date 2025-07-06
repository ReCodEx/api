<?php

namespace App\Helpers\Mocks;

use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\Mocks\MockUserStorage;
use App\V1Module\Presenters\BasePresenter;
use Nette\Application\Application;
use Nette\Application\PresenterFactory;
use Nette\Application\Routers\RouteList;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use Nette\Security\User;
use Nette;
use ReflectionProperty;

class MockHelper
{
    /**
     * Initializes a presenter object with empty http request, response, and user objects.
     * This is intended to be called right after presenter instantiation and before calling the Presenter::run method.
     * @param BasePresenter $presenter The presenter to be initialized.
     */
    public static function initPresenter(BasePresenter $presenter)
    {
        $httpRequest = new \Nette\Http\Request(new UrlScript());
        $httpResponse = new Response();
        $user = new User(new MockUserStorage());

        $application = new Application(new PresenterFactory(), new RouteList("V1"), $httpRequest, $httpResponse);
        $presenter->application = $application;

        $factory = new MockTemplateFactory();

        $presenter->injectPrimary($httpRequest, $httpResponse, user: $user, templateFactory: $factory);
    }
}
