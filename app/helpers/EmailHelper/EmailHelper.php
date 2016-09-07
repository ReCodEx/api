<?php

namespace App\Helpers;

use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Mail\SendException;
use Nette\Utils\Arrays;
use Latte;

class EmailHelper {

  /** @var IMailer */
  private $mailer;

  /** @var string */
  private $url;

  /** @var string */
  private $siteName;

  /** @var string */
  private $githubUrl;

  public function __construct(IMailer $mailer, array $params) {
    $this->mailer = $mailer;
    $this->url = Arrays::get($params, "url", "https://recodex.projekty.ms.mff.cuni.cz");
    $this->siteName = Arrays::get($params, "siteName", "ReCodEx");
    $this->githubUrl = Arrays::get($params, "githubUrl", "http://recodex.github.io");
  }

  /**
   * Send an email with a nice template.
   * @param string $from  Sender of the email
   * @param array  $to    Receivers of the $email
   * @param string $subject Subject of the email
   * @param string $text  Text of the message
   * @return bool
   */
  public function send(string $from, array $to, string $subject, string $text) {
    $latte = new Latte\Engine;
    $latte->setTempDirectory(__DIR__ . "/../../../temp");
    $params = [
      "subject"   => $subject,
      "message"   => $text,
      "url"       => $this->url,
      "siteName"  => $this->siteName,
      "githubUrl" => $this->githubUrl
    ];
    $html = $latte->renderToString(__DIR__ . "/email.latte", $params);

    $message = new Message;
    $message->setFrom($from)
      ->setSubject($subject)
      ->setHtmlBody($html);

    foreach ($to as $receiver) {
      $message->addTo($receiver);
    }

    try {
      $this->mailer->send($message);
      return TRUE;
    } catch (SendException $e) {
      // silent error
    }

    return FALSE;
  }

}
