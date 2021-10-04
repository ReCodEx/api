<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailLinkHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\ShadowAssignmentPoints;
use Nette\Utils\Arrays;

/**
 * Sending emails on assignment creation.
 */
class PointsChangedEmailsSender
{

    /** @var EmailHelper */
    private $emailHelper;

    /** @var string */
    private $sender;
    /** @var string */
    private $solutionPointsRedirectUrl;
    /** @var string */
    private $shadowPointsRedirectUrl;


    /**
     * Constructor.
     * @param EmailHelper $emailHelper
     * @param array $params
     */
    public function __construct(EmailHelper $emailHelper, array $params)
    {
        $this->emailHelper = $emailHelper;
        $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
        $this->solutionPointsRedirectUrl = Arrays::get(
            $params,
            ["solutionPointsRedirectUrl"],
            "https://recodex.mff.cuni.cz"
        );
        $this->shadowPointsRedirectUrl = Arrays::get(
            $params,
            ["shadowPointsRedirectUrl"],
            "https://recodex.mff.cuni.cz"
        );
    }

    /**
     * Notify solution author that his/her solution points were updated.
     * @param AssignmentSolution $solution
     * @return boolean
     * @throws InvalidStateException
     */
    public function solutionPointsUpdated(AssignmentSolution $solution): bool
    {
        if (
            $solution->getSolution()->getAuthor() === null ||
            $solution->getAssignment() === null ||
            $solution->getAssignment()->getGroup() === null
        ) {
            // group, assignment or user was deleted, do not send emails
            return false;
        }

        $author = $solution->getSolution()->getAuthor();
        if (!$author->isVerified() || !$author->getSettings()->getPointsChangedEmails()) {
            return true;
        }

        $locale = $author->getSettings()->getDefaultLanguage();
        $result = $this->createSolutionPointsUpdated($solution, $locale);

        return $this->emailHelper->send(
            $this->sender,
            [$author->getEmail()],
            $locale,
            $result->getSubject(),
            $result->getText()
        );
    }

    /**
     * Prepare and format body of the solution points updated mail.
     * @param AssignmentSolution $solution
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createSolutionPointsUpdated(AssignmentSolution $solution, string $locale): EmailRenderResult
    {
        $assignment = $solution->getAssignment();

        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/solutionPointsUpdated_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getLocalizedTexts()
                )->getName(),
                "group" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getGroup()->getLocalizedTexts()
                )->getName(),
                "points" => $solution->getPoints(),
                "maxPoints" => $solution->getMaxPoints(),
                "hasBonusPoints" => $solution->getBonusPoints() !== 0,
                "bonusPoints" => $solution->getBonusPoints(),
                "link" => EmailLinkHelper::getLink($this->solutionPointsRedirectUrl, ["id" => $assignment->getId()])
            ]
        );
    }

    /**
     * Send notification to a student who has been awarded points for shadow assignment.
     * @param ShadowAssignmentPoints $points
     * @return boolean
     * @throws InvalidStateException
     */
    public function shadowPointsUpdated(ShadowAssignmentPoints $points): bool
    {
        if (
            $points->getShadowAssignment() === null ||
            $points->getShadowAssignment()->getGroup() === null
        ) {
            // group or assignment was deleted, do not send emails
            return false;
        }

        $awardee = $points->getAwardee();
        if ($awardee === null) {
            // user was deleted, do not send emails
            return false;
        }

        if (!$awardee->isVerified() || !$awardee->getSettings()->getPointsChangedEmails()) {
            return true;
        }

        $locale = $awardee->getSettings()->getDefaultLanguage();
        $result = $this->createShadowPointsUpdated($points, $locale);

        return $this->emailHelper->send(
            $this->sender,
            [$awardee->getEmail()],
            $locale,
            $result->getSubject(),
            $result->getText()
        );
    }

    /**
     * Prepare and format body of the shadow assignment points updated mail.
     * @param ShadowAssignmentPoints $points
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createShadowPointsUpdated(ShadowAssignmentPoints $points, string $locale): EmailRenderResult
    {
        $assignment = $points->getShadowAssignment();

        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/shadowPointsUpdated_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getLocalizedTexts()
                )->getName(),
                "group" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getGroup()->getLocalizedTexts()
                )->getName(),
                "points" => $points->getPoints(),
                "maxPoints" => $assignment->getMaxPoints(),
                "link" => EmailLinkHelper::getLink($this->shadowPointsRedirectUrl, ["id" => $points->getId()])
            ]
        );
    }
}
