<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\GeneralStatsHelper;
use App\Helpers\GeneralStats;
use App\Helpers\EmailHelper;
use DateTime;
use DateInterval;
use Nette\Utils\Arrays;

/**
 * Sending emails with general statistics overview.
 */
class GeneralStatsEmailsSender {

  /** @var EmailHelper */
  private $emailHelper;

  /** @var string */
  private $sender;

  /** @var string[] */
  private $recipient;

  /** @var string */
  private $subject;

  /** @var string */
  private $period;

  /**
   * Constructor.
   * @param EmailHelper $emailHelper
   * @param array $params
   * @throws InvalidStateException
   */
  public function __construct(EmailHelper $emailHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $recipient = Arrays::get($params, ["emails", "to"]);
    if (!$recipient) {
      throw new InvalidStateException("Missing recipient (To) address in GeneralStatsEmailsSender configuration.");
    }
    $this->recipient = is_array($recipient) ? $recipient : [$recipient];
    $this->subject = Arrays::get($params, ["emails", "subject"], "General Status Overview");
    $this->period = Arrays::get($params, ["period"], "1 week");
  }

  /**
   * Send the stats extracted from the given helper.
   * @param GeneralStatsHelper $generalStatsHelper
   * @return bool
   */
  public function send(GeneralStatsHelper $generalStatsHelper): bool {
    $since = new DateTime();
    $since->sub(DateInterval::createFromDateString($this->period));
    $generalStats = $generalStatsHelper->gatherStats($since);
    list($subject, $text) = $this->createGeneralStats($generalStats);

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      $this->recipient,
      "en",
      $subject,
      $text
    );
  }

  /**
   * Prepare and format body of the mail
   * @param GeneralStats $generalStats
   * @return string[] list of subject and formatted mail body to be sent
   */
  private function createGeneralStats(GeneralStats $generalStats): array {
    $latte = EmailLatteFactory::latte();
    $values = (array)$generalStats;
    $values['period'] = $this->period;
    return $latte->renderEmail(__DIR__ . "/generalStats.latte", $values);
  }
}
