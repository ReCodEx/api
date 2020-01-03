<?php

namespace App\Helpers;

use limenet\GitVersion\Directory;
use limenet\GitVersion\Formatters\CustomFormatter;
use Nette;
use Nette\Utils\Arrays;

/**
 * Holder of configuration API item, which should describe this server.
 */
class ApiConfig
{
    use Nette\SmartObject;

    /**
     * Address of API server as visible from outside world.
     * @var string
     */
    protected $address;

    /**
     * Some basic name which should be used for identification of service
     * @var string
     */
    protected $name;

    /**
     * Basic description of this server.
     * @var string
     */
    protected $description;

    /**
     * Version of the project in semantic versioning (major.minor.patch)
     * @var string
     */
    protected $version;

    /**
     * Format in which version will be displayed
     * @var string
     */
    protected $versionFormat;

    /**
     * Constructs configuration object from given array.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->address = Arrays::get($config, ["address"]);
        $this->name = Arrays::get($config, ["name"]);
        $this->description = Arrays::get($config, ["description"]);
        $this->versionFormat = Arrays::get($config, ["versionFormat"], "{tag}");

        // version is constructed from git version tag
        $this->version = (new Directory(__DIR__))->get(new CustomFormatter($this->versionFormat)) ?: "UNKNOWN";
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getVersion()
    {
        return $this->version;
    }
}
