<?php

namespace App\Helpers\Mocks;

use Nette;
use Nette\Application\UI\Control;
use Nette\Application\UI\Template;
use Nette\Application\UI\TemplateFactory;
use Nette\Security\IIdentity;

class MockTemplateFactory implements TemplateFactory
{
    public function createTemplate(?Control $control = null): Template
    {
        return new MockTemplate();
    }
}
