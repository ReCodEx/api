<?php

namespace App\Helpers;

use App\Helpers\Emails\EmailLocalizationHelper;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Nette\Utils\Arrays;
use App\Model\Entity\ReportedErrors;

/**
 * Sending error reports to administrator by email.
 */
class FailureHelper
{

    public const TYPE_BACKEND_ERROR = "BACKEND ERROR";
    public const TYPE_API_ERROR = "API ERROR";

    /** @var EmailHelper Emails sending component */
    private $emailHelper;

    /** @var array List of email addresses which will receive the reports */
    private $receivers;

    /** @var string Sender address of all mails, something like "noreply@recodex.mff.cuni.cz" */
    private $sender;

    /** @var string Prefix of mail subject to be used */
    private $subjectPrefix;

    /** @var EntityManagerInterface Database entity manager */
    private $em;

    /**
     * Constructor
     * @param EntityManagerInterface $em Database entity manager
     * @param EmailHelper $emailHelper Instance of object which is able to sending mails
     * @param array $params Array of configurable options like destination addresses etc.
     */
    public function __construct(EntityManagerInterface $em, EmailHelper $emailHelper, array $params)
    {
        $this->em = $em;
        $this->emailHelper = $emailHelper;
        $this->receivers = Arrays::get($params, ["emails", "to"], ["admin@recodex.org"]);
        $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.org");
        $this->subjectPrefix = Arrays::get($params, ["emails", "subjectPrefix"], "Failure Report - ");

        if (!is_array($this->receivers)) {
            $this->receivers = [$this->receivers];
        }
    }

    /**
     * Report an issue in system to administrator
     * @param string $type Type of the error like backend error or api error
     * @param string $message Text of the error message
     * @return bool
     * @throws Exception
     */
    public function report(string $type, string $message)
    {
        $subject = $this->formatSubject($type);
        $recipients = implode(",", $this->receivers);

        // Save the report to the database
        $entry = new ReportedErrors($type, $recipients, $subject, $message);
        $this->em->persist($entry);
        $this->em->flush();

        // Send the mail
        return $this->emailHelper->send(
            $this->sender,
            $this->receivers,
            EmailLocalizationHelper::DEFAULT_LOCALE,
            $subject,
            $message
        );
    }

    /**
     * @param AssignmentSolutionSubmission|ReferenceSolutionSubmission $submission
     * @param string $type
     */
    public function reportSubmissionFailure($submission, string $type)
    {
        $failure = $submission->getFailure();
        if ($failure !== null) {
            $this->report(
                $type,
                sprintf(
                    "Failure of submission with ID '%s' and type '%s': %s",
                    $submission->getId(),
                    $submission->getJobType(),
                    $failure->getDescription()
                )
            );
        }
    }

    /**
     * Prepare mail subject for each error type
     * @param string $type Type of error
     * @return string Mail subject for this type of error
     */
    private function formatSubject(string $type): string
    {
        return $this->subjectPrefix . $type;
    }
}
