<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailRenderResult;
use App\Helpers\GeneralStatsHelper;
use App\Helpers\GeneralStats;
use App\Helpers\EmailHelper;
use DateTime;
use DateInterval;
use Nette\Utils\Arrays;

/**
 * Sending emails with warning that async worker may be down or overwhelmed.
 */
class AsyncJobsStuckEmailsSender
{
    /** @var EmailHelper */
    private $emailHelper;

    /** @var string */
    private $sender;

    /** @var string[] */
    private $recipient;

    /**
     * Constructor.
     * @param EmailHelper $emailHelper
     * @param array $params
     * @throws InvalidStateException
     */
    public function __construct(EmailHelper $emailHelper, array $params)
    {
        $this->emailHelper = $emailHelper;
        $this->sender = Arrays::get($params, ["from"], "noreply@recodex.mff.cuni.cz");
        $recipient = Arrays::get($params, ["to"]);
        if (!$recipient) {
            throw new InvalidStateException(
                "Missing email recipient (To) address in async jobs upkeep configuration."
            );
        }
        $this->recipient = is_array($recipient) ? $recipient : [$recipient];
    }

    /**
     * Send an alert that some async jobs may be stuck.
     * @param int $count number of jobs stuck
     * @param DateInterval $maxDelay the longes interval any job is stuck
     * @return bool
     */
    public function send(int $count, DateInterval $maxDelay): bool
    {
        $mail = $this->createStuckMail($count, $maxDelay);
        return $this->emailHelper->send(
            $this->sender,
            $this->recipient,
            "en",
            $mail->getSubject(),
            $mail->getText()
        );
    }

    /**
     * Prepare and format body of the mail
     * @param int $count number of jobs stuck
     * @param DateInterval $maxDelay the longes interval any job is stuck
     * @return EmailRenderResult
     */
    private function createStuckMail(int $count, DateInterval $maxDelay): EmailRenderResult
    {
        $latte = EmailLatteFactory::latte();
        $values = [
            'count' => $count,
            'maxDelay' => $maxDelay,
        ];
        return $latte->renderEmail(__DIR__ . "/asyncJobsStuck.latte", $values);
    }
}
