<?php

namespace App\Helpers\Mocks;

use Nette;
use Nette\Application\UI\Template;
use Nette\Application\UI\TemplateFactory;
use Nette\Security\IIdentity;

class MockTemplate implements Template
{
    public function render(): void
    {
    }

    public function setFile(string $file): static
    {
        return $this;
    }

    public function getFile(): ?string
    {
        return "test";
    }
}
