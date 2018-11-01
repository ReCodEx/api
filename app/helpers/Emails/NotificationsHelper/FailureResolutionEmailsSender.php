<?php
namespace App\Helpers\Notifications;

use App\Helpers\EmailHelper;
use App\Helpers\EmailLocalizationHelper;
use App\Helpers\Evaluation\IExercise;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionFailure;
use App\Model\Entity\User;
use Nette\SmartObject;
use Nette\Utils\Arrays;
use Latte;

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
  /** @var EmailLocalizationHelper */
  private $localizationHelper;

  /**
   * @param EmailHelper $emailHelper
   * @param EmailLocalizationHelper $localizationHelper
   * @param array $params
   */
  public function __construct(EmailHelper $emailHelper, EmailLocalizationHelper $localizationHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->localizationHelper = $localizationHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $this->failureResolvedPrefix = Arrays::get($params, ["emails", "failureResolvedPrefix"], "Submission Failure Resolved - ");
  }

  /**
   * Send a notification about a failure being resolved
   * @param SubmissionFailure $failure
   * @return bool
   */
  public function failureResolved(SubmissionFailure $failure): bool {
    $submission = $failure->getSubmission();

    /** @var LocalizedExercise $text */
    $text = $this->localizationHelper->getLocalization($submission->getExercise()->getLocalizedTexts());
    $title = $text !== null ? $text->getName() : "UNKNOWN";
    $subject = $this->failureResolvedPrefix . $title;

    return $this->emailHelper->send(
      $this->sender,
      [$submission->getAuthor()->getEmail()],
      $subject,
      $this->createFailureResolvedBody($failure, $title)
    );
  }

  private function createFailureResolvedBody(SubmissionFailure $failure, string $title): string {
    $latte = new Latte\Engine();
    return $latte->renderToString(__DIR__ . "/failureResolved.latte", [
      "title" => $title,
      "date" => $failure->getCreatedAt(),
      "note" => $failure->getResolutionNote()
    ]);
  }
}
