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

    /**
     * Injects a Format class to the FormatCache.
     * This method must not be used outside of testing, normal Format classes are discovered automatically.
     * @param string $format The Format class name.
     */
    public static function injectFormat(string $format)
    {
        // make sure the cache is initialized (it uses lazy loading)
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
    }
}
