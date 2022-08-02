<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\SubmissionFailure;
use Nette\SmartObject;
use Nette\Utils\Arrays;

/**
 * A helper for sending notifications when submission failures are resolved
 */
class FailureResolutionEmailsSender
{
    use SmartObject;

    /** @var EmailHelper */
    private $emailHelper;

    /** @var string */
    private $sender;


    /**
     * @param array $params
     * @param EmailHelper $emailHelper
     */
    public function __construct(array $params, EmailHelper $emailHelper)
    {
        $this->emailHelper = $emailHelper;
        $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex");
    }

    /**
     * Send a notification about a failure being resolved
     * @param SubmissionFailure $failure
     * @return bool
     * @throws InvalidStateException
     */
    public function failureResolved(SubmissionFailure $failure): bool
    {
        $submission = $failure->getSubmission();
        $locale = $submission->getAuthor()->getSettings()->getDefaultLanguage();

        /** @var ?LocalizedExercise $text */
        $text = EmailLocalizationHelper::getLocalization($locale, $submission->getExercise()->getLocalizedTexts());
        $title = $text !== null ? $text->getName() : "UNKNOWN";
        $result = $this->createFailureResolved($failure, $title, $locale);

        return $this->emailHelper->send(
            $this->sender,
            [$submission->getAuthor()->getEmail()],
            $locale,
            $result->getSubject(),
            $result->getText()
        );
    }

    /**
     * @param SubmissionFailure $failure
     * @param string $title
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createFailureResolved(SubmissionFailure $failure, string $title, string $locale): EmailRenderResult
    {
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/failureResolved_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "title" => $title,
                "date" => $failure->getCreatedAt(),
                "note" => $failure->getResolutionNote()
            ]
        );
    }
}
