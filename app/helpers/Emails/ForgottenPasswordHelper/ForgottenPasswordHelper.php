<?php

namespace App\Helpers;

use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Helpers\WebappLinks;
use App\Security\TokenScope;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Nette\Utils\Arrays;
use App\Model\Entity\Login;
use App\Model\Entity\ForgottenPassword;
use App\Security\AccessManager;
use DateTime;
use DateInterval;

/**
 * Provides all necessary things which are needed on forgotten password request.
 */
class ForgottenPasswordHelper
{
    /**
     * Database entity manager
     * @var EntityManagerInterface
     */
    private $em;

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
     * Expiration period of the change-password token in seconds
     * @var int
     */
    private $tokenExpiration;

    /**
     * @var AccessManager
     */
    private $accessManager;

    /** @var WebappLinks */
    private $webappLinks;

    /**
     * Constructor
     * @param array $notificationsConfig Parameters from configuration file
     * @param EntityManagerInterface $em
     * @param EmailHelper $emailHelper
     * @param AccessManager $accessManager
     */
    public function __construct(
        array $notificationsConfig,
        EntityManagerInterface $em,
        EmailHelper $emailHelper,
        AccessManager $accessManager,
        WebappLinks $webappLinks
    ) {
        $this->em = $em;
        $this->emailHelper = $emailHelper;
        $this->accessManager = $accessManager;
        $this->webappLinks = $webappLinks;
        $this->sender = Arrays::get($notificationsConfig, ["emails", "from"], "noreply@recodex");
        $this->tokenExpiration = Arrays::get(
            $notificationsConfig,
            ["tokenExpiration"],
            600 // default value: 10 minutes
        );
    }

    /**
     * Generate access token and send it to the given email.
     * @param Login $login
     * @param string $IPaddress IP address of change request client (from request headers)
     * @return bool If sending was successful or not
     * @throws Exception
     */
    public function process(Login $login, string $IPaddress)
    {
        // Stalk forgotten password requests a little bit and store them to database
        $entry = new ForgottenPassword(
            $login->getUser(),
            $login->getUser()->getEmail(),
            $IPaddress
        );
        $this->em->persist($entry);
        $this->em->flush();

        // prepare all necessary things
        $token = $this->accessManager->issueToken(
            $login->getUser(),
            null,
            [TokenScope::CHANGE_PASSWORD],
            $this->tokenExpiration
        );

        $locale = $login->getUser()->getSettings()->getDefaultLanguage();
        $result = $this->createEmail($login, $locale, $token);

        // Send the mail
        return $this->emailHelper->send(
            $this->sender,
            [$login->getUser()->getEmail()],
            $locale,
            $result->getSubject(),
            $result->getText()
        );
    }

    /**
     * Creates and return body of email message.
     * @param Login $login
     * @param string $locale
     * @param string $token
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createEmail(Login $login, string $locale, string $token): EmailRenderResult
    {
        // show to user a minute less, so he doesn't waste time ;-)
        $exp = $this->tokenExpiration - 60;
        $expiresAfter = (new DateTime())->add(new DateInterval("PT{$exp}S"));

        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/resetPasswordEmail_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "username" => $login->getUsername(),
                "link" => $this->webappLinks->getForgottenPasswordUrl($token),
                "expiresAfter" => $expiresAfter->format("H:i")
            ]
        );
    }
}
