<?php

namespace App\Helpers\Emails;

use Latte\Engine;

/**
 *
 */
class EmailLatteWrapper
{

    private $latte;

    public function __construct(Engine $latte)
    {
        $this->latte = $latte;
    }

    /**
     * Returns array with two elements, first one is subject of the mail, second one text of the email.
     * @return EmailRenderResult
     */
    public function renderEmail($name, array $params = [], $block = null): EmailRenderResult
    {
        EmailLatteExtension::setSubject(null);
        $text = $this->latte->renderToString($name, $params, $block); // has to be called before retrieving subject
        return new EmailRenderResult(EmailLatteExtension::getLastSubject(), $text);
    }
}
