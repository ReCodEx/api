<?php

namespace App\Model\Entity;

use App\Helpers\EntityMetadata\Solution\SolutionParams;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;
use App\Helpers\Yaml;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="solution_created_at_idx", columns={"created_at"})})
 */
class Solution implements JsonSerializable
{
    use CreateableEntity;

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

    public function getAuthor(): ?User
    {
        return $this->author->isDeleted() ? null : $this->author;
    }

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
     * @ORM\Column(type="text")
     */
    protected $solutionParams;

    /**
     * @ORM\Column(type="string")
     * Subdirectory in path to the ZIP archive where all solution files are.
     * The subdir names are typically time-related (e.g., YYYY-MM) to optimize backup management.
     */
    protected $subdir;

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
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
    public function __construct(User $author, RuntimeEnvironment $runtimeEnvironment)
    {
        $this->author = $author;
        $this->files = new ArrayCollection();
        $this->evaluated = false;
        $this->createdAt = new DateTime();
        $this->runtimeEnvironment = $runtimeEnvironment;
        $this->solutionParams = "";
        $this->subdir = $this->createdAt->format('Y-m');
    }

    public function addFile(SolutionFile $file)
    {
        $this->files->add($file);
    }

    /**
     * Get names of the file which belongs to solution.
     * @return string[]
     */
    public function getFileNames(): array
    {
        return $this->files->map(
            function (SolutionFile $file) {
                return $file->getName();
            }
        )->toArray();
    }

    public function getSolutionParams(): SolutionParams
    {
        return new SolutionParams(Yaml::parse($this->solutionParams));
    }

    public function setSolutionParams(SolutionParams $params)
    {
        $dumped = Yaml::dump($params->toArray());
        $this->solutionParams = $dumped ?: "";
    }

    public function getSubdir(): ?string
    {
        return $this->subdir;
    }

    /**
     * Specialized getter that checks whether the solution has a single ZIP archive as a file and returns it.
     * @return SolutionZipFile|null null is returned if the solution does not have single archive
     */
    public function getSolutionZipFile(): ?SolutionZipFile
    {
        return (count($this->files) === 1 && $this->files->first() instanceof SolutionZipFile)
            ? $this->files->first() : null;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function getRuntimeEnvironment(): RuntimeEnvironment
    {
        return $this->runtimeEnvironment;
    }

    public function setEvaluated(bool $evaluated): void
    {
        $this->evaluated = $evaluated;
    }
}
