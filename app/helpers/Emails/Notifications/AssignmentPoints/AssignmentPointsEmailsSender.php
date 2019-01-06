<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailLinkHelper;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\ShadowAssignmentPoints;
use Nette\Utils\Arrays;

/**
 * Sending emails on assignment creation.
 */
class AssignmentPointsEmailsSender {

  /** @var EmailHelper */
  private $emailHelper;

  /** @var string */
  private $sender;
  /** @var string */
  private $pointsUpdatedPrefix;
  /** @var string */
  private $shadowPointsUpdatedPrefix;
  /** @var string */
  private $assignmentPointsRedirectUrl;
  /** @var string */
  private $shadowPointsRedirectUrl;


  /**
   * Constructor.
   * @param EmailHelper $emailHelper
   * @param array $params
   */
  public function __construct(EmailHelper $emailHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $this->pointsUpdatedPrefix = Arrays::get($params, ["emails", "pointsUpdatedPrefix"], "Assignment Points Updated - ");
    $this->shadowPointsUpdatedPrefix = Arrays::get($params, ["emails", "shadowPointsUpdatedPrefix"], "Shadow Assignment Points Awarded - ");
    $this->assignmentPointsRedirectUrl = Arrays::get($params, ["assignmentPointsRedirectUrl"], "https://recodex.mff.cuni.cz");
    $this->shadowPointsRedirectUrl = Arrays::get($params, ["shadowPointsRedirectUrl"], "https://recodex.mff.cuni.cz");
  }

  /**
   * Assignment was created, send emails to users who wanted to know this
   * situation.
   * @param AssignmentSolution $solution
   * @return boolean
   * @throws InvalidStateException
   */
  public function assignmentPointsUpdated(AssignmentSolution $solution): bool {
    $author = $solution->getSolution()->getAuthor();
    if (!$author->getSettings()->getAssignmentPointsEmails()) {
      return true;
    }

    $locale = $author->getSettings()->getDefaultLanguage();
    $subject = $this->pointsUpdatedPrefix .
      EmailLocalizationHelper::getLocalization($locale, $solution->getAssignment()->getLocalizedTexts())->getName();

    return $this->emailHelper->send(
      $this->sender,
      [$author->getEmail()],
      $locale,
      $subject,
      $this->createAssignmentPointsUpdatedBody($solution, $locale)
    );
  }

  /**
   * Prepare and format body of the assignment points updated mail.
   * @param AssignmentSolution $solution
   * @param string $locale
   * @return string Formatted mail body to be sent
   * @throws InvalidStateException
   */
  private function createAssignmentPointsUpdatedBody(AssignmentSolution $solution, string $locale): string {
    $assignment = $solution->getAssignment();

    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/assignmentPointsUpdated_{locale}.latte");
    return $latte->renderToString($template, [
      "assignment" => EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName(),
      "group" => EmailLocalizationHelper::getLocalization($locale, $assignment->getGroup()->getLocalizedTexts())->getName(),
      "points" => $solution->getPoints(),
      "maxPoints" => $solution->getMaxPoints(),
      "hasBonusPoints" => $solution->getBonusPoints() !== 0,
      "bonusPoints" => $solution->getBonusPoints(),
      "link" => EmailLinkHelper::getLink($this->assignmentPointsRedirectUrl, ["id" => $assignment->getId()])
    ]);
  }

  /**
   * Assignment was created, send emails to users who wanted to know this
   * situation.
   * @param ShadowAssignmentPoints $points
   * @return boolean
   * @throws InvalidStateException
   */
  public function shadowPointsUpdated(ShadowAssignmentPoints $points): bool {
    $awardee = $points->getAwardee();
    if (!$awardee->getSettings()->getAssignmentPointsEmails()) {
      return true;
    }

    $locale = $awardee->getSettings()->getDefaultLanguage();
    $subject = $this->shadowPointsUpdatedPrefix .
      EmailLocalizationHelper::getLocalization($locale, $points->getShadowAssignment()->getLocalizedTexts())->getName();

    return $this->emailHelper->send(
      $this->sender,
      [$awardee->getEmail()],
      $locale,
      $subject,
      $this->createShadowPointsUpdatedBody($points, $locale)
    );
  }

  /**
   * Prepare and format body of the shadow assignment points updated mail.
   * @param ShadowAssignmentPoints $points
   * @param string $locale
   * @return string Formatted mail body to be sent
   * @throws InvalidStateException
   */
  private function createShadowPointsUpdatedBody(ShadowAssignmentPoints $points, string $locale): string {
    $assignment = $points->getShadowAssignment();

    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/shadowPointsUpdated_{locale}.latte");
    return $latte->renderToString($template, [
      "assignment" => EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName(),
      "group" => EmailLocalizationHelper::getLocalization($locale, $assignment->getGroup()->getLocalizedTexts())->getName(),
      "points" => $points->getPoints(),
      "maxPoints" => $assignment->getMaxPoints(),
      "link" => EmailLinkHelper::getLink($this->shadowPointsRedirectUrl, ["id" => $points->getId()])
    ]);
  }
}