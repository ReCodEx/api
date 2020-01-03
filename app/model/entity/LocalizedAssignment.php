<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use JsonSerializable;

/**
 * @ORM\Entity
 * @method string getStudentHint()
 */
class LocalizedAssignment extends LocalizedEntity implements JsonSerializable
{
    /**
     * @ORM\ManyToOne(targetEntity="LocalizedAssignment")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @var LocalizedAssignment
     */
    protected $createdFrom;

    /**
     * Separate text visible to students which is kept separately from the exercise specification,
     * so it will not be overriten every time an assigment is synced with exercise.
     * @ORM\Column(type="text")
     */
    protected $studentHint;

    public function __construct(string $locale, string $studentHint, ?LocalizedAssignment $createdFrom = null)
    {
        parent::__construct($locale);
        $this->studentHint = $studentHint;
        $this->createdFrom = $createdFrom;
    }

    public function equals(LocalizedEntity $entity): bool
    {
        return $entity instanceof LocalizedAssignment && $entity->getStudentHint() === $this->getStudentHint();
    }

    public function setCreatedFrom(LocalizedEntity $entity)
    {
        if ($entity instanceof LocalizedAssignment) {
            $this->createdFrom = $entity;
        } else {
            throw new InvalidArgumentException("Wrong type of entity supplied");
        }
    }

    public function jsonSerialize()
    {
        return [
            "id" => $this->id,
            "locale" => $this->locale,
            "studentHint" => $this->studentHint
        ];
    }
}
