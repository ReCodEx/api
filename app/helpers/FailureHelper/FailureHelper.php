<?php

namespace App\Helpers;

use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Utils\Arrays;
use Latte;

class FailureHelper {

  const TYPE_BACKEND_ERROR = "BACKEND ERROR";
  const TYPE_API_ERROR = "API ERROR";

  /** @var EmailHelper */
  private $emailHelper;

  /** @var string */
  private $receivers;

  /** @var string */
  private $sender;

  /** @var string */
  private $subjectPrefix;

  public function __construct(EmailHelper $emailHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->receivers = Arrays::get($params, ["emails", "to"], [ "admin@recodex.org" ]);
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.org");
    $this->subjectPrefix = Arrays::get($params, ["emails", "subjectPrefix"], "ReCodEx Failure Report - ");

    if (!is_array($this->receivers)) {
      $this->receivers = [ $this->receivers ];
    }
  }

  public function report(string $type, string $message) {
    // @todo: Save the report to the database

    return $this->emailHelper->send(
      $this->sender,
      $this->receivers,
      $this->formatSubject($type),
      $this->formatBody($message)
    );
  }

  private function formatSubject(string $type): string {
    return $type; // @todo
  }

  private function formatBody(string $message): string {
    return $message; // @todo
  }

}
