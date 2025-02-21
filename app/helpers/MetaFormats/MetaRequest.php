<?php

namespace App\Helpers\MetaFormats;

use Nette\Application\Request;

class MetaRequest
{
    private Request $baseRequest;
    private mixed $requestFormatInstance;

    public function __construct(Request $request, mixed $requestFormatInstance)
    {
        $this->baseRequest = $request;
        $this->requestFormatInstance = $requestFormatInstance;
    }

    /**
     * Retrieve the presenter name.
     */
    public function getPresenterName(): string
    {
        return $this->baseRequest->getPresenterName();
    }

    /**
     * Returns all variables provided to the presenter (usually via URL).
     */
    public function getParameters(): array
    {
        return $this->baseRequest->getParameters();
    }

    /**
     * Returns a parameter provided to the presenter.
     */
    public function getParameter(string $key): mixed
    {
        return $this->baseRequest->getParameter($key);
    }

    /**
     * Returns a variable provided to the presenter via POST.
     * If no key is passed, returns the entire array.
     */
    public function getPost(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->requestFormatInstance;
        }

        return $this->requestFormatInstance->$key;
    }

    /**
     * Returns all uploaded files.
     */
    public function getFiles(): array
    {
        return $this->baseRequest->getFiles();
    }

    /**
     * Returns the method.
     */
    public function getMethod(): ?string
    {
        return $this->baseRequest->getMethod();
    }


    /**
     * Checks if the method is the given one.
     */
    public function isMethod(string $method): bool
    {
        return $this->baseRequest->isMethod($method);
    }

    /**
     * Checks the flag.
     */
    public function hasFlag(string $flag): bool
    {
        return $this->baseRequest->hasFlag($flag);
    }


    public function toArray(): array
    {
        return $this->baseRequest->toArray();
    }
}
