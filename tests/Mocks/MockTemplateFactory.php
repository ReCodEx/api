<?php

use Nette\Application\UI\Control;
use Nette\Application\UI\Template;
use Nette\Application\UI\TemplateFactory;

class MockTemplateFactory implements TemplateFactory
{
    public function createTemplate(?Control $control = null): Template
    {
        return new MockTemplate();
    }
}
