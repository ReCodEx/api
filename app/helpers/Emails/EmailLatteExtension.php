<?php

namespace App\Helpers\Emails;

use Latte;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\AuxiliaryNode;
use Generator;

/**
 * Extension class that is registered into latte to handle email subjects.
 * (introduces {emailSubject} tag which captures the subject of rendered email templates)
 */
class EmailLatteExtension extends Latte\Extension
{
    /**
     * Holds last scraped subject from email template.
     * @var string|null
     */
    private static $subject = null;

    /**
     * Used both by the extension and to re-set the subjec before rendering.
     * @param string|null $subject
     */
    public static function setSubject(?string $subject): void
    {
        self::$subject = $subject;
    }

    /**
     * Get last stored subject.
     * @return string|null
     */
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

    public function createEmailSubject(Tag $tag): Generator
    {
        [$content, $endTag] = yield; // wait for the closing tag and get the contents

        // convert content node into raw text wrapped in string (scalar) node
        $textContext = new PrintContext(Latte\ContentType::Text);
        $contentNode = new StringNode($content->print($textContext));

        // auxiliary node creates PHP content based on given lambda (print) function
        return new AuxiliaryNode(
            fn(PrintContext $context) =>
                $context->format('\App\Helpers\Emails\EmailLatteExtension::setSubject(%node);', $contentNode)
        );
    }
}
