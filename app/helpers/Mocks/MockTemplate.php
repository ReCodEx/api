<?php

namespace App\Helpers\Mocks;

use Nette\Application\UI\Template;

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
