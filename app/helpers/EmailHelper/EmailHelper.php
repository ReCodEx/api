<?php

namespace App\Helpers;

use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Mail\SendException;
use Nette\Utils\Arrays;
use Latte;

/**
 * Wrapper for email communication. It can send messages with predefined values
 * using nice template.
 */
class EmailHelper {

  /** @var IMailer Nette mailer component */
  private $mailer;

  /** @var string Url of api instance */
  private $apiUrl;

  /** @var string Url which will be mentioned in mail footer */
  private $footerUrl;

  /** @var string Name of the frontend interface, defaults to "ReCodEx" */
  private $siteName;

  /** @var string Url of project page (GitHub hosted), defaults to "https://github.com/ReCodEx" */
  private $githubUrl;

  /**
   * Constructor
   * @param IMailer $mailer Created and configured (TLS verification, etc.) mailer object
   * @param array   $params Array of params used to fill information into predefined mail template
   */
  public function __construct(IMailer $mailer, array $params) {
    $this->mailer = $mailer;
    $this->apiUrl = Arrays::get($params, "apiUrl", "https://recodex.projekty.ms.mff.cuni.cz:4000");
    $this->footerUrl = Arrays::get($params, "footerUrl", "https://recodex.projekty.ms.mff.cuni.cz");
    $this->siteName = Arrays::get($params, "siteName", "ReCodEx");
    $this->githubUrl = Arrays::get($params, "githubUrl", "https://github.com/ReCodEx");
  }

  /**
   * Send an email with a nice template.
   * @param string $from    Sender of the email
   * @param array  $to      Receivers of the email
   * @param string $subject Subject of the email
   * @param string $text    Text of the message
   * @return bool  If sending was successful or not
   */
  public function send(string $from, array $to, string $subject, string $text) {
    $latte = new Latte\Engine;
    $latte->setTempDirectory(__DIR__ . "/../../../temp");
    $params = [
      "subject"   => $subject,
      "message"   => $text,
      "apiUrl"    => $this->apiUrl,
      "footerUrl" => $this->footerUrl,
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
