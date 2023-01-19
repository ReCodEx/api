<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={
 *   "batch_id", "author_id", "solution_file_id", "file_entry"
 * })})
 * A record (node) representing a similarity detected between a file and a set of files of a particular user.
 * This entity holds the tested file and a reference to the author of similar files (possible sources of plagiarism).
 * There should be at least one PlagiarismDetectedSimilarFile record associated with detected similarty
 * (i.e., all possible sources of plagiarism of one author).
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
     * Author of all solutions refered in related PlagiarismDetectedSimilarFile entities.
     * (i.e., this is just a denormalization pull-up to increase efficiency).
     */
    protected $author;

    /**
     * @var AssignmentSolution
     * @ORM\ManyToOne(targetEntity="AssignmentSolution")
     * The solution that was tested for similarities.
     */
    protected $testedSolution;

    /**
     * @var SolutionFile
     * @ORM\ManyToOne(targetEntity="SolutionFile")
     * Reference to a solution file that was tested for similarities (is a suspected plagiarism).
     */
    protected $solutionFile;

    /**
     * @ORM\Column(type="string")
     * A submitted file name (of the solution) that was tested for similarities.
     * This is filled only if solution file is a ZIP that was scanned internally.
     */
    protected $fileEntry;

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
     * Constructor of similarity node representing aggregated references to sources of another user.
     * @param PlagiarismDetectionBatch $batch
     * @param User|null $author
     * @param AssignmentSolution $testedSolution
     * @param SolutionFile $solutionFile
     * @param string $fileEntry
     * @param float $similarity
     */
    public function __construct(
        PlagiarismDetectionBatch $batch,
        ?User $author,
        AssignmentSolution $testedSolution,
        SolutionFile $solutionFile,
        string $fileEntry,
        float $similarity,
    ) {
        $this->batch = $batch;
        $this->author = $author;
        $this->testedSolution = $testedSolution;
        $this->solutionFile = $solutionFile;
        $this->fileEntry = $fileEntry;
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
            "solutionFileId" => $this->getSolutionFile()->getId(),
            "fileEntry" => $this->getFileEntry(),
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

    public function getSolutionFile(): SolutionFile
    {
        return $this->solutionFile;
    }

    public function getFileEntry(): string
    {
        return $this->fileEntry;
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
