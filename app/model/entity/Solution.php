<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;
use DateTime;

/**
 * @ORM\Entity
 * @method string getId()
 * @method User getAuthor()
 * @method Collection getFiles()
 * @method RuntimeEnvironment getRuntimeEnvironment()
 * @method void setEvaluated(bool)
 */
class Solution implements JsonSerializable
{
  use MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $author;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\OneToMany(targetEntity="SolutionFile", mappedBy="solution")
   */
  protected $files;

  /**
   * @ORM\ManyToOne(targetEntity="RuntimeEnvironment")
   */
  protected $runtimeEnvironment;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $evaluated;


  /**
   * @return array
   */
  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "userId" => $this->author->getId(),
      "createdAt" => $this->createdAt->getTimestamp(),
      "files" => $this->files->getValues()
    ];
  }

  /**
   * Constructor
   * @param User $author The user who submits the solution
   * @param RuntimeEnvironment $runtimeEnvironment
   */
  public function __construct(User $author, RuntimeEnvironment $runtimeEnvironment) {
    $this->author = $author;
    $this->files = new ArrayCollection;
    $this->evaluated = false;
    $this->createdAt = new DateTime;
    $this->runtimeEnvironment = $runtimeEnvironment;
  }

  public function addFile(SolutionFile $file) {
    $this->files->add($file);
  }

  /**
   * Get names of the file which belongs to solution.
   * @return string[]
   */
  public function getFileNames(): array {
    return $this->files->map(function (SolutionFile $file) {
      return $file->getName();
    })->toArray();
  }

}
