<?php

namespace App\Helpers\Emails;

use Latte;
use Latte\Engine;
use Generator;

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
        EmailLatteExtension::setSubject(null);
        $text = $this->latte->renderToString($name, $params, $block); // has to be called before retrieving subject
        return new EmailRenderResult(EmailLatteExtension::getLastSubject(), $text);
    }
}

class EmailLatteExtension extends Latte\Extension
{
    /**
     * Holds last scraped subject from email template.
     * @var string|null
     */
    private static $subject = null;

    public static function setSubject(?string $subject): void
    {
        self::$subject = $subject;
    }

    public static function getLastSubject(): ?string
    {
        return self::$subject;
    }

    /*
     * Extension stuff
     */

    public function getTags(): array
    {
        // registers a new tag {emailSubject}
        return [
            'emailSubject' => [$this, 'createEmailSubject'],
        ];
    }

    public function createEmailSubject(Latte\Compiler\Tag $tag): Generator
    {
        [$content, $endTag] = yield; // wait for the closing tag and get the contents

        // convert content node into raw text wrapped in string (scalar) node
        $textContext = new Latte\Compiler\PrintContext(Latte\ContentType::Text);
        $contentNode = new Latte\Compiler\Nodes\Php\Scalar\StringNode($content->print($textContext));

        // auxiliary node creates PHP content based on given lambda (print) function
        return new Latte\Compiler\Nodes\AuxiliaryNode(
            fn(Latte\Compiler\PrintContext $context) =>
                $context->format('\App\Helpers\Emails\EmailLatteExtension::setSubject(%node);', $contentNode)
        );
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

        // extra tag(s) for emails
        //$latte->addMacro("emailSubject", EmailMacros::install($latte->getCompiler()));
        $latte->addExtension(new EmailLatteExtension());

        // filters
        $latte->addFilter(
            "localizedDate",
            function ($date, $locale) {
                if ($locale === EmailLocalizationHelper::CZECH_LOCALE) {
                    return Latte\Essential\Filters::date($date, 'j.n.Y H:i');
                }

                return Latte\Essential\Filters::date($date, 'n/j/Y H:i');
            }
        );

        $latte->addFilter(
            "relativeDateTime",
            function ($dateDiff, $locale) {
                return EmailLocalizationHelper::getDateIntervalLocalizedString($dateDiff, $locale);
            }
        );

        return new LatteWrapper($latte);
    }
}
