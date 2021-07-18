<?php

namespace App\Model\Entity;

use App\Exceptions\ExerciseConfigException;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use App\Helpers\YamlException;
use App\Helpers\Yaml;
use DateTime;

/**
 * @ORM\Entity
 */
class ExerciseConfig
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="text")
     */
    protected $config;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="ExerciseConfig")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $createdFrom;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $author;

    public function getAuthor(): ?User
    {
        return $this->author->isDeleted() ? null : $this->author;
    }

    /**
     * Constructor
     * @param string $config
     * @param User $author
     * @param ExerciseConfig|null $createdFrom
     */
    public function __construct(
        string $config,
        User $author,
        ExerciseConfig $createdFrom = null
    ) {
        $this->createdAt = new DateTime();

        $this->config = $config;
        $this->createdFrom = $createdFrom;
        $this->author = $author;
    }

    /**
     * Return array-like structure containing config.
     * @return array|string
     * @throws ExerciseConfigException
     */
    public function getParsedConfig()
    {
        try {
            return Yaml::parse($this->config);
        } catch (YamlException $e) {
            throw new ExerciseConfigException("Exercise configuration is not a valid YAML and it cannot be parsed.");
        }
    }

    public function equals(?ExerciseConfig $config): bool
    {
        if ($config === null) {
            return false;
        }

        try {
            return Yaml::dump($this->getParsedConfig()) === Yaml::dump($config->getParsedConfig());
        } catch (ExerciseConfigException $exception) {
            return false;
        }
    }

    ////////////////////////////////////////////////////////////////////////////

    public function getId(): ?string
    {
        return $this->id;
    }
}
