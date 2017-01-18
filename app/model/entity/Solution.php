<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;

/**
 * @ORM\Entity
 * @method string getId()
 * @method Collection getFiles()
 * @method RuntimeConfig getRuntimeConfig()
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
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    /**
     * @ORM\OneToMany(targetEntity="SolutionFile", mappedBy="solution")
     */
    protected $files;

    /**
     * @ORM\ManyToOne(targetEntity="RuntimeConfig")
     */
    protected $runtimeConfig;

    public function getHardwareGroupId() {
      return $this->runtimeConfig->getHardwareGroup()->getId();
    }

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
        "userId" => $this->user->getId(),
        "files" => $this->files->getValues()
      ];
    }

    /**
     * @param User $user          The user who submits the solution
     * @param array $files
     * @param RuntimeConfig $runtimeConfig
     */
    public function __construct(User $user, RuntimeConfig $runtimeConfig) {
      $this->user = $user;
      $this->files = new ArrayCollection;
      $this->evaluated = FALSE;
      $this->runtimeConfig = $runtimeConfig;
    }

  public function addFile(SolutionFile $file)
  {
    $this->files->add($file);
  }
}
