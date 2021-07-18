<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class ReportedErrors
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $type;

    /**
     * @ORM\Column(type="string")
     */
    protected $recipients;

    /**
     * @ORM\Column(type="string")
     */
    protected $subject;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $sentAt;

    /**
     * @ORM\Column(type="text")
     */
    protected $description;

    public function __construct(string $type, string $recipients, string $subject, string $description)
    {
        $this->type = $type;
        $this->recipients = $recipients;
        $this->subject = $subject;
        $this->sentAt = new DateTime();
        $this->description = $description;
    }
}
