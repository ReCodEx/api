<?php

namespace App\Model\Entity;

use App\Helpers\Yaml;
use App\Helpers\YamlException;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class ExerciseScoreConfig implements JsonSerializable
{
    use CreatableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @return string
     */
    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    /**
     * @ORM\Column(type="string")
     * Identifier of the calculator
     */
    protected $calculator;

    public function getCalculator(): string
    {
        return $this->calculator;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     * Calculator configuration data.
     */
    protected $config;

    public function getConfig(): ?string
    {
        return $this->config;
    }

    public function getConfigParsed()
    {
        try {
            return $this->config ? Yaml::parse($this->config) : null;
        } catch (YamlException $e) {
            return null;  // this should never happen, but let's do this defensively...
        }
    }

    /**
     * Compare this config with given config structure
     * @return bool
     */
    public function configEquals($config): bool
    {
        $serialized = $config !== null ? Yaml::dump($config) : null;
        return $serialized === $this->config;
    }

    /**
     * @ORM\ManyToOne(targetEntity="ExerciseScoreConfig")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $createdFrom;

    /**
     * Constructor
     * @param string $calculator
     * @param mixed $config Configuration will be encoded to Yaml internally
     * @param ExerciseScoreConfig|null $createdFrom
     */
    public function __construct(
        string $calculator = "",
        $config = null,
        ?ExerciseScoreConfig $createdFrom = null
    ) {
        $this->createdAt = new DateTime();

        $this->calculator = $calculator;
        $this->config = $config !== null ? Yaml::dump($config) : null;
        $this->createdFrom = $createdFrom;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'calculator' => $this->getCalculator(),
            'config' => $this->getConfigParsed(),
            'createdAt' => $this->getCreatedAt()->getTimestamp(),
        ];
    }
}
