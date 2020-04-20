<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class ExerciseScoreConfig implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
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

    public function getConfig(): string
    {
        return $this->config;
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @ORM\ManyToOne(targetEntity="ExerciseScoreConfig")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $createdFrom;

    /**
     * Constructor
     * @param string $calculator
     * @param string $config
     * @param ExerciseScoreConfig|null $createdFrom
     */
    public function __construct(
        string $calculator = "",
        string $config = null,
        ExerciseScoreConfig $createdFrom = null
    ) {
        $this->createdAt = new DateTime();

        $this->calculator = $calculator;
        $this->config = $config;
        $this->createdFrom = $createdFrom;
    }

    public function jsonSerialize()
    {
        return [
            'calculator' => $this->getCalculator(),
            'config' => $this->getConfig(),
            'createdAt' => $this->getCreatedAt()->getTimestamp(),
        ];
    }
}
