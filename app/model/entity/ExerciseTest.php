<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;


/**
 * @ORM\Entity
 * @method int getId()
 * @method setId(int $id)
 * @method string getName()
 * @method string getDescription()
 * @method User getAuthor()
 * @method DateTime getCreatedAt()
 * @method string setName(string $name)
 * @method string setDescription(string $description)
 * @method void setUpdatedAt(DateTime $date)
 */
class ExerciseTest implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="integer")
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $author;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $updatedAt;

  /**
   * ExerciseTest constructor.
   * @param string $name
   * @param string $description
   * @param User $author
   */
  public function __construct(string $name, string $description, User $author) {
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;

    $this->name = $name;
    $this->description = $description;
    $this->author = $author;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "description" => $this->description
    ];
  }
}
