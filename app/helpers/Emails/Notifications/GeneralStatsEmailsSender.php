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
    $result = $this->createGeneralStats($generalStats);

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      $this->recipient,
      "en",
      $result->getSubject(),
      $result->getText()
    );
  }

  /**
   * Prepare and format body of the mail
   * @param GeneralStats $generalStats
   * @return EmailRenderResult
   */
  private function createGeneralStats(GeneralStats $generalStats): EmailRenderResult {
    $latte = EmailLatteFactory::latte();
    $values = (array)$generalStats;
    $values['period'] = $this->period;
    return $latte->renderEmail(__DIR__ . "/generalStats.latte", $values);
  }
}
