<?php
namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\SubmissionFailure;
use Nette\SmartObject;
use Nette\Utils\Arrays;

/**
 * A helper for sending notifications when submission failures are resolved
 */
class FailureResolutionEmailsSender {
  use SmartObject;

  /** @var EmailHelper */
  private $emailHelper;

  /** @var string */
  private $sender;
  /** @var string */
  private $failureResolvedPrefix;


  /**
   * @param EmailHelper $emailHelper
   * @param array $params
   */
  public function __construct(EmailHelper $emailHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $this->failureResolvedPrefix = Arrays::get($params, ["emails", "failureResolvedPrefix"], "Submission Failure Resolved - ");
  }

  /**
   * Send a notification about a failure being resolved
   * @param SubmissionFailure $failure
   * @return bool
   * @throws InvalidStateException
   */
  public function failureResolved(SubmissionFailure $failure): bool {
    $submission = $failure->getSubmission();
    $locale = $submission->getAuthor()->getSettings()->getDefaultLanguage();

    /** @var LocalizedExercise $text */
    $text = EmailLocalizationHelper::getLocalization($locale, $submission->getExercise()->getLocalizedTexts());
    $title = $text !== null ? $text->getName() : "UNKNOWN";
    $subject = $this->failureResolvedPrefix . $title;

    return $this->emailHelper->send(
      $this->sender,
      [$submission->getAuthor()->getEmail()],
      $locale,
      $subject,
      $this->createFailureResolvedBody($failure, $title, $locale)
    );
  }

  /**
   * @param SubmissionFailure $failure
   * @param string $title
   * @param string $locale
   * @return string
   * @throws InvalidStateException
   */
  private function createFailureResolvedBody(SubmissionFailure $failure, string $title, string $locale): string {
    $latte = EmailLatteFactory::latte();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/failureResolved_{locale}.latte");
    return $latte->renderToString($template, [
      "title" => $title,
      "date" => $failure->getCreatedAt(),
      "note" => $failure->getResolutionNote()
    ]);
  }
}
