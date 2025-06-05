<?php

namespace App\Helpers\Mocks;

use App\Helpers\Mocks\MockUserStorage;
use App\Security\UserStorage;
use App\V1Module\Presenters\BasePresenter;
use App\V1Module\Presenters\RegistrationPresenter;
use Nette\Application\Application;
use Nette\Application\PresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\Routers\RouteList;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use Nette\Security\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use Nette;
use Nette\Security\IIdentity;

class MockHelper
{
    /**
     * Initializes a presenter object with empty http request, response, and user objects.
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
