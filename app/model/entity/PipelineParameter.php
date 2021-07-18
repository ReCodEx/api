<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn("discriminator")
 */
abstract class PipelineParameter implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Pipeline", inversedBy="parameters")
     */
    protected $pipeline;

    /**
     * @ORM\Column(type="string")
     */
    protected $name;

    public function __construct(Pipeline $pipeline, string $name)
    {
        $this->pipeline = $pipeline;
        $this->name = $name;
    }

    abstract public function getValue();

    abstract public function setValue($value);

    public function jsonSerialize()
    {
        return $this->getValue();
    }

    ////////////////////////////////////////////////////////////////////////////

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPipeline(): Pipeline
    {
        return $this->pipeline;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
