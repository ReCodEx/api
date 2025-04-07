<?php

namespace App\Model\Entity;

use App\Exceptions\ExerciseConfigException;
use Doctrine\ORM\Mapping as ORM;
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
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
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

    /**
     * Low level function that overrides existing config.
     * This should be used only in very special situations (e.g., cli command that is fixing something)!
     * @param array $parsedConfig config represented as structure of arrays
     */
    public function overrideConfig(array $parsedConfig): void
    {
        $this->config = Yaml::dump($parsedConfig);
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }
}
