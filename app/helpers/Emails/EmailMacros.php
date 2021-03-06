<?php

namespace App\Helpers\Emails;

use Latte;
use Latte\MacroNode;
use Latte\Macros\MacroSet;

class EmailSubject
{

    /**
     * Holds last scraped subject from email template.
     * @var string|null
     */
    public static $subject;

    public static function clear()
    {
        self::$subject = null;
    }
}

class EmailMacros extends MacroSet
{

    public static function install(Latte\Compiler $compiler): MacroSet
    {
        $set = new EmailMacros($compiler);
        $set->addMacro('emailSubject', [$set, 'macroEmailSubject'], [$set, 'macroEndEmailSubject']);
        return $set;
    }

    public function macroEmailSubject(MacroNode $node, Latte\PhpWriter $writer)
    {
        // given lambda which returns null protects ob_start() against flush when error occurs
        return 'ob_start(function () { return null; })';
    }

    public function macroEndEmailSubject(MacroNode $node, Latte\PhpWriter $writer)
    {
        return $writer->write("\App\Helpers\Emails\EmailMacros::captureEmailSubject(ob_get_clean());");
    }

    public static function captureEmailSubject($subject)
    {
        EmailSubject::$subject = (string)$subject;
    }
}
