<?php

namespace App\Helpers;

use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Mail\SendException;
use Nette\Utils\Arrays;
use Exception;

/**
 * Wrapper for email communication. It can send messages with predefined values
 * using nice template.
 */
class EmailHelper
{
    /** @var Mailer Nette mailer component */
    private $mailer;

    /** @var string Url of api instance */
    private $apiUrl;

    /** @var string Url which will be mentioned in mail footer */
    private $footerUrl;

    /** @var string Name of the frontend interface, defaults to "ReCodEx" */
    private $siteName;

    /** @var string Url of project page (GitHub hosted), defaults to "https://github.com/ReCodEx" */
    private $githubUrl;

    /** @var string Address from which emails should be sent if from is not provided */
    private $from;

    /** @var string Prefix of mail subject to be used */
    private $subjectPrefix;

    /** @var bool Whether the ReCodEx mailing subsystem is in debug mode. Debug mode prevents sending anything via SMTP. */
    private $debugMode;

    /** @var string Path to archive directory. If set, copies of all emails are logged there in text files. */
    private $archivingDir;

    /** @var bool Whether the template should show info about notification user settings. */
    private $showSettingsInfo = false;

    /**
     * Constructor
     * @param Mailer $mailer Created and configured (TLS verification, etc.) mailer object
     * @param array $params Array of params used to fill information into predefined mail template
     */
    public function __construct(array $params, Mailer $mailer)
    {
        $this->mailer = $mailer;
        $this->apiUrl = Arrays::get($params, "apiUrl", "https://recodex.mff.cuni.cz/api");
        $this->footerUrl = Arrays::get($params, "footerUrl", "https://recodex.mff.cuni.cz");
        $this->siteName = Arrays::get($params, "siteName", "ReCodEx");
        $this->githubUrl = Arrays::get($params, "githubUrl", "https://github.com/ReCodEx");
        $this->from = Arrays::get($params, "from", "ReCodEx <noreply@recodex>");
        $this->subjectPrefix = Arrays::get($params, "subjectPrefix", "ReCodEx - ");
        $this->debugMode = Arrays::get($params, "debugMode", false);
        $this->archivingDir = Arrays::get($params, "archivingDir", "");
    }

    /**
     * @param bool $show whether to show settings info or not
     * @return EmailHelper $this to support chaining
     */
    public function setShowSettingsInfo(bool $show = true): EmailHelper
    {
        $this->showSettingsInfo = $show;
        return $this;
    }

    /**
     * Send an email with a nice template from default email address.
     * @param array $to Receivers of the email
     * @param string $locale locale of the email which should be sent
     * @param string $subject Subject of the email
     * @param string $text Text of the message
     * @param array $bcc Blind copy receivers
     * @return bool If sending was successful or not
     * @throws InvalidStateException
     */
    public function sendFromDefault(array $to, string $locale, string $subject, string $text, array $bcc = [])
    {
        return $this->send(null, $to, $locale, $subject, $text, $bcc);
    }


    /**
     * Save mail copy into a text file in archiving directory.
     * @param Message $message Message to be serialized into the file.
     * @param Exception $lastMailerException Exception thrown from the sender (if any). Its message is logged as well.
     * @throws Exception
     */
    private function archiveMailCopy(Message $message, Exception $lastMailerException = null)
    {
        if (!file_exists($this->archivingDir)) {
            mkdir($this->archivingDir, 0775, true); // make sure logging directory exists
        }

        // Logging is not that important, all following opertions fail silently.
        if (file_exists($this->archivingDir) && is_writeable($this->archivingDir)) {
            $data = [
                '----- BEGIN MAIL -----',
                $_SERVER['REQUEST_URI'] ?? 'CLI',
            ];
            if (!empty($lastMailerException)) {
                $data[] = $lastMailerException->getMessage();
            }

            $data[] = '-----';
            $data[] = $message->generateMessage();
            $data[] = "----- END MAIL -----\n"; // trailing separator (in case multiple emails are logged)

            $fileName = $this->archivingDir . "/" . (new \DateTime())->format("Y-m-d-His") . ".emaildump";
            file_put_contents($fileName, join("\n", $data), FILE_APPEND);
        }
    }


    /**
     * Send an email with a nice template.
     * @param string|null $from Sender of the email
     * @param array $to Receivers of the email
     * @param string $locale locale of the email which should be sent
     * @param string $subject Subject of the email
     * @param string $text Text of the message
     * @param array $bcc Blind copy receivers
     * @return bool If sending was successful or not
     * @throws InvalidStateException
     * @throws Exception
     */
    public function send(?string $from, array $to, string $locale, string $subject, string $text, array $bcc = [])
    {
        $subject = $this->subjectPrefix . $subject;
        if ($from === null) {
            // if from email is not provided use the default one
            $from = $this->from;
        }

        $latte = EmailLatteFactory::latte();
        $params = [
            "subject" => $subject,
            "message" => $text,
            "apiUrl" => $this->apiUrl,
            "footerUrl" => $this->footerUrl,
            "siteName" => $this->siteName,
            "githubUrl" => $this->githubUrl,
            "showSettingsInfo" => $this->showSettingsInfo,
        ];
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/email_{locale}.latte");
        $result = $latte->renderEmail($template, $params);

        // Prepare the message ...
        $message = new Message();
        $message->setFrom($from)
            ->setSubject($subject)
            ->setHtmlBody($result->getText());

        foreach ($to as $receiver) {
            $message->addTo($receiver);
        }

        foreach ($bcc as $receiver) {
            $message->addBcc($receiver);
        }

        // Send it via SMTP ...
        $lastMailerException = null;
        if (!$this->debugMode) {
            try {
                $this->mailer->send($message);
            } catch (SendException $e) {
                $lastMailerException = $e;
            }
        }

        // Archive a copy of the message into a file ...
        if ($this->archivingDir) {
            $this->archiveMailCopy($message, $lastMailerException);
        }

        return empty($lastMailerException);
    }
}
