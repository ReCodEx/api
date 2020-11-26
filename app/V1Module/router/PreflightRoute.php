<?php

namespace App\V1Module\Router;

use Nette;
use Nette\Http\IRequest;
use Nette\Application;
use Nette\Application\Request;
use Nette\Http\UrlScript;
use Nette\Utils\Strings;

/**
 * HTTP Options request route aka. preflight requests.
 */
class PreflightRoute implements Nette\Routing\Router
{

    /** @var string */
    private $prefix;

    /** @var string */
    private $presenter;

    /** @var string */
    private $action;

    /**
     * @param string $prefix Prefix of the URL
     * @param string $destination Handler name
     */
    public function __construct(string $prefix, string $destination)
    {
        $this->prefix = Strings::startsWith("/", $prefix) ? $prefix : "/$prefix";
        list($presenter, $action) = Nette\Application\Helpers::splitName($destination);
        $this->presenter = $presenter;
        $this->action = $action;
    }

    /**
     * Maps HTTP request to a Request object.
     * @return array|null
     */
    public function match(IRequest $httpRequest): ?array
    {
        $isOptions = $httpRequest->isMethod("OPTIONS");
        $path = $httpRequest->getUrl()->getPath();
        if (!$isOptions || !Strings::startsWith($path, $this->prefix)) {
            return null;
        }

        return new Request(
            $this->presenter,
            'OPTIONS',
            [Application\UI\Presenter::ACTION_KEY => $this->action],
            [],
            [],
            [Request::SECURED => $httpRequest->isSecured()]
        );
    }

    /**
     * Constructs absolute URL from Request object.
     * @return string|null
     */
    public function constructUrl(array $params, UrlScript $refUrl): ?string
    {
        return null;
    }
}
