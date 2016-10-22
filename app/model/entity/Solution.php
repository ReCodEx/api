<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;
use Nette\Utils\Json;
use Nette\Utils\Arrays;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\MalformedJobConfigException;
use App\Exceptions\SubmissionFailedException;

use GuzzleHttp\Exception\RequestException;

/**
 * @ORM\Entity
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
     * @ORM\OneToMany(targetEntity="UploadedFile", mappedBy="solution")
     */
    protected $files;

    /**
     * @ORM\ManyToOne(targetEntity="SolutionRuntimeConfig")
     */
    protected $solutionRuntimeConfig;

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
        "userId" => $this->getUser()->getId(),
        "files" => $this->getFiles()->getValues()
      ];
    }

    /**
     * @param User $user          The user who submits the solution
     * @param string $hardwareGroup
     * @param array $files
     */
    public function __construct(User $user, array $files, SolutionRuntimeConfig $solutionRuntimeConfig) {
      $this->user = $user;
      $this->files = new ArrayCollection;
      $this->evaluated = FALSE;
      $this->solutionRuntimeConfig = $solutionRuntimeConfig;
      foreach ($files as $file) {
        if ($file->getSolution() !== NULL && $file->getSolution()->getEvaluated() === TRUE) {
          // the file was already used before and that is not allowed
          throw new BadRequestException("The file {$file->getId()} was already used in a different submission. If you want to use this file, reupload it to the server.");
        }

        $this->files->add($file);
        $file->solution = $this;
      }
    }

}
