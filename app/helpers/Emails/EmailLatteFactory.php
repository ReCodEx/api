<?php

namespace App\Helpers\Emails;

use Latte;
use Latte\Engine;

/**
 * Holds result of rendering email, namely it contains subject and text of the email.
 */
class EmailRenderResult
{

    private $subject;
    private $text;

    public function __construct(?string $subject, ?string $text)
    {
        $this->subject = $subject;
        $this->text = $text;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getText(): ?string
    {
        return $this->text;
    }
}

class LatteWrapper
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
        EmailSubject::clear();
        $text = $this->latte->renderToString($name, $params, $block); // has to be called before retrieving subject
        return new EmailRenderResult(EmailSubject::$subject, $text);
    }
}

/**
 * Factory for latte engine which can be used in email senders.
 */
class EmailLatteFactory
{

    /**
     * Create latte engine for email templates with helper filters.
     * @return LatteWrapper
     */
    public static function latte(): LatteWrapper
    {
        $latte = new Engine();
        $latte->setTempDirectory(__DIR__ . "/../../../temp");

        // macros
        $latte->addMacro("emailSubject", EmailMacros::install($latte->getCompiler()));

        // filters
        $latte->addFilter(
            "localizedDate",
            function ($date, $locale) {
                if ($locale === EmailLocalizationHelper::CZECH_LOCALE) {
                    return Latte\Runtime\Filters::date($date, 'j.n.Y H:i');
                }

                return Latte\Runtime\Filters::date($date, 'n/j/Y H:i');
            }
        );

        return new LatteWrapper($latte);
    }
}
