<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DEPRECATED (replaced by group external attributes)
 */
#[ORM\Entity]
class SisGroupBinding
{
    /**
     * @var \Ramsey\Uuid\UuidInterface
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    /**
     * @var Group
     */
    #[ORM\ManyToOne(targetEntity: Group::class)]
    protected $group;

    #[ORM\Column(type: 'string')]
    protected $code;

    /**
     * SisGroupBinding constructor.
     * @param Group $group
     * @param string $code
     */
    public function __construct($group, $code)
    {
        $this->group = $group;
        $this->code = $code;
    }

    public function getGroup(): ?Group
    {
        return $this->group->isDeleted() ? null : $this->group;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function __toString()
    {
        return $this->code;
    }
}
