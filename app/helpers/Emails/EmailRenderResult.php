<?php

namespace App\Helpers\Emails;

/**
 * Helper class that holds result of email rendering, namely it contains the subject and the text of the email.
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
