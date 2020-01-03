<?php

namespace App\Helpers;

use Nette;
use Nette\Utils\Arrays;

/**
 * General configuration for all emails sent from this API.
 */
class EmailsConfig
{
    use Nette\SmartObject;

    /**
     * Address where this api server runs.
     * @var string
     */
    protected $apiUrl;

    /**
     * Address which is used in footer of email.
     * @var string
     */
    protected $footerUrl;

    /**
     * Human readable identifier of site.
     * @var string
     */
    protected $siteName;

    /**
     * Github address where this project can be found.
     * @var string
     */
    protected $githubUrl;

    /**
     * From whom emails will be sent.
     * @var string
     */
    protected $from;

    /**
     * Constructs configuration object from given array.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->apiUrl = Arrays::get($config, ["apiUrl"]);
        $this->footerUrl = Arrays::get($config, ["footerUrl"]);
        $this->siteName = Arrays::get($config, ["siteName"]);
        $this->githubUrl = Arrays::get($config, ["githubUrl"]);
        $this->from = Arrays::get($config, ["from"]);
    }

    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    public function getFooterUrl()
    {
        return $this->footerUrl;
    }

    public function getSiteName()
    {
        return $this->siteName;
    }

    public function getGithubUrl()
    {
        return $this->githubUrl;
    }

    public function getFrom()
    {
        return $this->from;
    }
}
