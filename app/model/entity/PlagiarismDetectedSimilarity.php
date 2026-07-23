<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * A record (node) representing a similarity detected between a file and a set of files of a particular user.
 * This entity holds the tested file and a reference to the author of similar files (possible sources of plagiarism).
 * There should be at least one PlagiarismDetectedSimilarFile record associated with detected similarity
 * (i.e., all possible sources of plagiarism of one author).
 */
#[ORM\Table]
#[ORM\UniqueConstraint(columns: ['batch_id', 'author_id', 'solution_file_id', 'file_entry'])]
#[ORM\Entity]
class PlagiarismDetectedSimilarity
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
     * @var PlagiarismDetectionBatch
     * Reference to a batch in which this similarity was detected.
     */
    #[ORM\ManyToOne(targetEntity: PlagiarismDetectionBatch::class)]
    protected $batch;

    /**
     * @var User
     * Author of all solutions referred in related PlagiarismDetectedSimilarFile entities.
     * (i.e., this is just a denormalization pull-up to increase efficiency).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $author;

    /**
     * @var AssignmentSolution
     * The solution that was tested for similarities.
     */
    #[ORM\ManyToOne(targetEntity: AssignmentSolution::class)]
    protected $testedSolution;

    /**
     * @var SolutionFile
     * Reference to a solution file that was tested for similarities (is a suspected plagiarism).
     */
    #[ORM\ManyToOne(targetEntity: SolutionFile::class)]
    protected $solutionFile;

    /**
     * A submitted file name (of the solution) that was tested for similarities.
     * This is filled only if solution file is a ZIP that was scanned internally.
     */
    #[ORM\Column(type: 'string')]
    protected $fileEntry;

    /**
     * Similarity score normalized from 0 (no similarity) to 1 (identical).
     */
    #[ORM\Column(type: 'float')]
    protected $similarity;

    /**
     * @var Collection
     */
    #[ORM\OneToMany(
        targetEntity: PlagiarismDetectedSimilarFile::class,
        mappedBy: 'detectedSimilarity',
        cascade: ['persist'],
        orphanRemoval: true
    )]
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
