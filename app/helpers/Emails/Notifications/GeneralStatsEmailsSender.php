<?php

namespace App\Helpers\Notifications;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailLinkHelper;
use App\Helpers\GeneralStatsHelper;
use App\Helpers\GeneralStats;
use App\Model\Entity\AssignmentSolutionSubmission;
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
   * @throws ExerciseConfigException
   */
  public function __construct(EmailHelper $emailHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $recipient = Arrays::get($params, ["emails", "to"]);
    if (!$recipient) {
      throw new ExerciseConfigException("Missing recipient (To) address in GeneralStatsEmailsSender configuration.");
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

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      $this->recipient,
      "en",
      $this->subject,
      $this->createGeneralStatsBody($generalStats)
    );
  }

  /**
   * Prepare and format body of the mail
   * @param GeneralStats $generalStats
   * @return string Formatted mail body to be sent
   */
  private function createGeneralStatsBody(GeneralStats $generalStats): string {
    $latte = EmailLatteFactory::latte();
    $values = (array)$generalStats;
    $values['period'] = $this->period;
    return $latte->renderToString(__DIR__ . "/generalStats.latte", $values);
  }
}
