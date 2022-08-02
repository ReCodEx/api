<?php

namespace App\Helpers;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;

/**
 * Wraps generators of webapp URLs.
 * Currently, the URL templates are in config and must be in sync with the webapp router.
 * This may change in the future...
 */
class WebappLinks
{
    /** @var string */
    private $assignmentUrl;

    /** @var string */
    private $shadowAssignmentUrl;

    /** @var string */
    private $solutionUrl;

    /** @var string */
    private $referenceSolutiontUrl;

    /** @var string */
    private $forgottenPasswordUrl;

    /** @var string */
    private $emailVerificationUrl;

    /**
     * Helper function that constructs links from templates.
     * @param string $link template
     * @param array $vars assoc. array of variables to be replaced in the template
     * @return string the link
     */
    private static function getLink(string $link, array $vars): string
    {
        foreach ($vars as $var => $val) {
            $link = Strings::replace($link, "/\{$var\}/", $val);
        }
        return $link;
    }

    public function __construct(string $webappUrl, array $linkTemplates)
    {
        $this->assignmentUrl = Arrays::get($linkTemplates, ["assignmentUrl"], "$webappUrl/app/assignment/{id}");
        $this->shadowAssignmentUrl = Arrays::get(
            $linkTemplates,
            ["shadowAssignmentUrl"],
            "$webappUrl/app/shadow-assignment/{id}"
        );
        $this->solutionUrl = Arrays::get(
            $linkTemplates,
            ["solutionUrl"],
            "$webappUrl/app/assignment/{assignmentId}/solution/{solutionId}"
        );
        $this->referenceSolutiontUrl = Arrays::get(
            $linkTemplates,
            ["referenceSolutiontUrl"],
            "$webappUrl/app/exercises/{exerciseId}/reference-solution/{solutionId}"
        );
        $this->forgottenPasswordUrl = Arrays::get(
            $linkTemplates,
            ["forgottenPasswordUrl"],
            "$webappUrl/forgotten-password/change?{token}"
        );
        $this->emailVerificationUrl = Arrays::get(
            $linkTemplates,
            ["emailVerificationUrl"],
            "$webappUrl/email-verification?{token}"
        );
    }

    /**
     * @param string $assignmentId
     * @return string URL to the assignment page
     */
    public function getAssignmentPageUrl(string $assignmentId): string
    {
        return self::getLink($this->assignmentUrl, [ 'id' => $assignmentId ]);
    }

    /**
     * @param string $assignmentId (shadow assignment)
     * @return string URL to the shadow assignment page
     */
    public function getShadowAssignmentPageUrl(string $assignmentId): string
    {
        return self::getLink($this->shadowAssignmentUrl, [ 'id' => $assignmentId ]);
    }

    /**
     * @param string $assignmentId
     * @param string $solutionId
     * @return string URL to the solution detail page
     */
    public function getSolutionPageUrl(string $assignmentId, string $solutionId): string
    {
        return self::getLink($this->solutionUrl, [ 'assignmentId' => $assignmentId, 'solutionId' => $solutionId ]);
    }

    /**
     * @param string $exerciseId
     * @param string $solutionId
     * @return string URL to the reference solution detail page
     */
    public function getReferenceSolutionPageUrl(string $exerciseId, string $solutionId): string
    {
        return self::getLink(
            $this->referenceSolutiontUrl,
            [ 'exerciseId' => $exerciseId, 'solutionId' => $solutionId ]
        );
    }

    /**
     * @param string $token JWT used to reset the password
     * @return string URL that can be used to reset the password
     */
    public function getForgottenPasswordUrl(string $token): string
    {
        return self::getLink($this->forgottenPasswordUrl, [ 'token' => $token ]);
    }

    /**
     * @param string $token JWT used to veify the email
     * @return string URL that can be used to verify an email
     */
    public function getEmailVerificationUrl(string $token): string
    {
        return self::getLink($this->emailVerificationUrl, [ 'token' => $token ]);
    }
}
