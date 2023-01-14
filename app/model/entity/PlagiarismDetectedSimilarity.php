<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * A record representing a similarity detected between a file and a set of files of a particular user.
 * This entity holds the tested file and a reference to the author of similar files.
 * There should be at least one PlagiarismDetectedSimilarFile record associated with detected similarty.
 */
class PlagiarismDetectedSimilarity implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @var PlagiarismDetectionBatch
     * @ORM\ManyToOne(targetEntity="PlagiarismDetectionBatch")
     * Reference to a batch in which this similarity was detected.
     */
    protected $batch;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="User")
     * Author of all solutions refered in related similar files.
     */
    protected $author;

    /**
     * @var AssignmentSolution
     * @ORM\ManyToOne(targetEntity="AssignmentSolution")
     * The solution that was tested for similarities.
     */
    protected $testedSolution;

    /**
     * @ORM\Column(type="string")
     * A submitted file name (of the solution) that was tested for similarities.
     */
    protected $testedFile;

    /**
     * @ORM\Column(type="float")
     * Similarity score normalized from 0 (no similarity) to 1 (identical).
     */
    protected $similarity;

    /**
     * @ORM\OneToMany(targetEntity="PlagiarismDetectedSimilarFile", mappedBy="detectedSimilarity",
     *                cascade={"persist"}, orphanRemoval=true)
     * @var Collection
     */
    protected $similarFiles;

    /**
     * Similarity with another user entity constructor.
     * @param PlagiarismDetectionBatch $batch
     * @param User|null $author
     * @param AssignmentSolution $testedSolution
     * @param string $testedFile
     * @param float $similarity
     */
    public function __construct(
        PlagiarismDetectionBatch $batch,
        ?User $author,
        AssignmentSolution $testedSolution,
        string $testedFile,
        float $similarity,
    ) {
        $this->batch = $batch;
        $this->author = $author;
        $this->testedSolution = $testedSolution;
        $this->testedFile = $testedFile;
        $this->similarity = $similarity;
        $this->similarFiles = new ArrayCollection();
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "batchId" => $this->getBatch()->getId(),
            "authorId" => $this->getAuthor() ? $this->getAuthor()->getId() : null,
            "testedSolutionId" => $this->getTestedSolution()->getId(),
            "testedFile" => $this->getTestedFile(),
            "similarity" => $this->getSimilarity(),
            "files" => $this->getSimilarFiles()->toArray(),
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getBatch(): PlagiarismDetectionBatch
    {
        return $this->batch;
    }

    public function getAuthor(): ?User
    {
        return $this->author === null || $this->author->isDeleted() ? null : $this->author;
    }

    public function getTestedSolution(): AssignmentSolution
    {
        return $this->testedSolution;
    }

    public function getTestedFile(): string
    {
        return $this->testedFile;
    }

    public function getSimilarity(): float
    {
        return $this->similarity;
    }

    public function getSimilarFiles(): Collection
    {
        return $this->similarFiles;
    }

    public function addSimilarFile(PlagiarismDetectedSimilarFile $similarFile): void
    {
        $this->similarFiles->add($similarFile);
    }
}
