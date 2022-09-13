<?php

namespace App\Helpers;

use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Helpers\WebappLinks;
use App\Security\InvitationToken;
use App\Model\Entity\User;
use Exception;
use Nette\Utils\Arrays;
use App\Security\AccessManager;
use DateTime;
use DateInterval;

/**
 * Provides all necessary things which are needed on forgotten password request.
 */
class InvitationHelper
{
    /**
     * Emails sending component
     * @var EmailHelper
     */
    private $emailHelper;

    /**
     * Sender address of all mails, something like "noreply@recodex"
     * @var string
     */
    private $sender;

    /**
     * @var AccessManager
     */
    private $accessManager;

    /**
     * @var WebappLinks
     */
    private $webappLinks;

    /**
     * Constructor
     * @param array $config parameters from configuration file
     * @param EmailHelper $emailHelper
     * @param AccessManager $accessManager
     * @param WebappLinks $webappLinks
     */
    public function __construct(
        array $config,
        EmailHelper $emailHelper,
        AccessManager $accessManager,
        WebappLinks $webappLinks
    ) {
        $this->emailHelper = $emailHelper;
        $this->accessManager = $accessManager;
        $this->webappLinks = $webappLinks;
        $this->sender = Arrays::get($config, ["emails", "from"], "noreply@recodex");
    }

    /**
     * Generate access token and send it to the given email.
     * @param string $instanceId
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @param string $titlesBefore
     * @param string $titlesAfter
     * @param string[] $groupsIds list of IDs where the user is added after registration
     * @param User $host who makes the invitation
     * @param string $locale language of the invitation email
     * @throws Exception
     */
    public function invite(
        string $instanceId,
        string $email,
        string $firstName,
        string $lastName,
        string $titlesBefore,
        string $titlesAfter,
        array $groupsIds,
        User $host,
        string $locale = "en"
    ) {
        $token = $this->accessManager->issueInvitationToken(
            $instanceId,
            $email,
            $firstName,
            $lastName,
            $titlesBefore,
            $titlesAfter,
            $groupsIds,
        );

        // yes, it is a bit odd to decode the token that was just created, but it is the easiest way how to implement
        // this and we can also check the token will be decodeable
        $decodedToken = $this->accessManager->decodeInvitationToken($token);

        // prepare and send the email
        $result = $this->createEmail($locale, "$firstName $lastName", $decodedToken->getExpireAt(), $token, $host);
        return $this->emailHelper->send(
            $this->sender,
            [$email],
            $locale,
            $result->getSubject(),
            $result->getText()
        );
    }

    /**
     * Creates and return body of email message.
     * @param string $locale
     * @param string $token
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createEmail(
        string $locale,
        string $username,
        DateTime $expireAt,
        string $token,
        User $host
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/invitationEmail_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "username" => $username,
                "link" => $this->webappLinks->getInvitationUrl($token),
                "expireAt" => $expireAt,
                "host" => $host->getName(),
                "hostmail" => $host->getEmail(),
            ]
        );
    }
}
