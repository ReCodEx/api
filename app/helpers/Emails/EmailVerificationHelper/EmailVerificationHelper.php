<?php

namespace App\Helpers;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Helpers\WebappLinks;
use App\Security\TokenScope;
use Exception;
use Latte;
use Nette\Utils\Arrays;
use App\Model\Entity\User;
use App\Security\AccessToken;
use App\Security\AccessManager;
use DateTime;
use DateInterval;

/**
 * Provides all necessary things which are needed on email verification request.
 */
class EmailVerificationHelper
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
     * Expiration period of the token in seconds
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
     * @param EmailHelper $emailHelper
     * @param AccessManager $accessManager
     * @param WebappLinks $webappLinks
     */
    public function __construct(
        array $notificationsConfig,
        EmailHelper $emailHelper,
        AccessManager $accessManager,
        WebappLinks $webappLinks
    ) {
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
     * @param User $user
     * @param bool $firstTime True if this is the first time the verification is requested
     *                        (account has just been created).
     * @return bool If sending was successful or not
     * @throws InvalidStateException
     */
    public function process(User $user, bool $firstTime = false)
    {
        // prepare all necessary things
        $token = $this->accessManager->issueToken(
            $user,
            null,
            [TokenScope::EMAIL_VERIFICATION],
            $this->tokenExpiration,
            ["email" => $user->getEmail()]
        );

        return $this->sendEmail($user, $token, $firstTime);
    }

    /**
     * Verify email verification token against given user.
     * @param User $user
     * @param AccessToken $token
     * @return bool
     * @throws ForbiddenRequestException
     * @throws InvalidAccessTokenException
     * @throws InvalidArgumentException
     */
    public function verify(User $user, AccessToken $token)
    {
        // the token is parsed, which means, it has already been validated in terms of exp, iat, ...
        // the only verification steps are:
        // 1] correct scope
        // 2] the IDs and emails of the user and the token are the same

        if (!$token->isInScope(TokenScope::EMAIL_VERIFICATION)) {
            throw new ForbiddenRequestException("You cannot verify email with this access token.");
        }

        return $user->getId() === $token->getUserId() && $user->getEmail() === $token->getPayload("email");
    }

    /**
     * Send an email with the token for the verification of the email address of the user.
     * @param User $user
     * @param string $token
     * @param bool $firstTime
     * @return bool
     * @throws InvalidStateException
     * @throws Exception
     */
    private function sendEmail(User $user, string $token, bool $firstTime = false): bool
    {
        $locale = $user->getSettings()->getDefaultLanguage();
        $result = $this->createEmail($user, $locale, $token, $firstTime);

        // Send the mail
        return $this->emailHelper->send(
            $this->sender,
            [$user->getEmail()],
            $locale,
            $result->getSubject(),
            $result->getText()
        );
    }

    /**
     * Creates and return body of email message.
     * @param User $user
     * @param string $locale
     * @param string $token
     * @param bool $firstTime
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createEmail(User $user, string $locale, string $token, bool $firstTime): EmailRenderResult
    {
        // show to user a minute less, so he doesn't waste time ;-)
        $exp = $this->tokenExpiration - 60;
        $expiresAfter = (new DateTime())->add(new DateInterval("PT{$exp}S"));

        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/verificationEmail_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "email" => $user->getEmail(),
                "link" => $this->webappLinks->getEmailVerificationUrl($token),
                "expiresAfter" => $expiresAfter->format("H:i"),
                "firstTime" => $firstTime,
            ]
        );
    }
}
