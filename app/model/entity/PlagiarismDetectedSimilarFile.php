<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Helpers\Plagiarism\SimilarityFragments;

#[ORM\Table]
#[ORM\UniqueConstraint(columns: ['detected_similarity_id', 'solution_file_id', 'file_entry'])]
#[ORM\Entity]
class PlagiarismDetectedSimilarFile
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
     * @var PlagiarismDetectedSimilarity
     * Reference to a detected similarity record to which this file reference contribute.
     */
    #[ORM\ManyToOne(targetEntity: PlagiarismDetectedSimilarity::class)]
    protected $detectedSimilarity;

    /**
     * @var AssignmentSolution
     * Another solution in which a similar code was detected.
     * Note: this is nullable in case other sources than assignment solutions are introduced in testing pool.
     */
    #[ORM\ManyToOne(targetEntity: AssignmentSolution::class)]
    protected $solution;

    /**
     * @var SolutionFile
     * Reference to a solution file where similarities were found.
     * If missing, external sources for comparison were used (and fileEntry is the only identification)
     */
    #[ORM\ManyToOne(targetEntity: SolutionFile::class)]
    protected $solutionFile;

    /**
     * Either a relative path within a ZIP file (if the solution file refers to the only ZIP archive),
     * or a reference to external file source that were used for comparison (preferably an URL).
     */
    #[ORM\Column(type: 'string')]
    protected $fileEntry;

    /**
     * JSON encoded structure containing the individual code fragments
     * (as byte-offset references to tested and similar files).
     */
    #[ORM\Column(type: 'text', length: 65535)]
    protected $fragments;

    /**
     * Similarity file record constructor
     * @param PlagiarismDetectedSimilarity $detectedSimilarity
     * @param AssignmentSolution|null $solution
     * @param SolutionFile|null $solutionFile
     * @param string $fileEntry
     * @param array $fragments
     */
    public function __construct(
        PlagiarismDetectedSimilarity $detectedSimilarity,
        ?AssignmentSolution $solution,
        ?SolutionFile $solutionFile,
        string $fileEntry,
        array $fragments,
    ) {
        $this->detectedSimilarity = $detectedSimilarity;
        $this->solution = $solution;
        $this->solutionFile = $solutionFile;
        $this->fileEntry = $fileEntry;
        $this->setFragments($fragments);

        $detectedSimilarity->addSimilarFile($this);
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getDetectedSimilarity(): PlagiarismDetectedSimilarity
    {
        return $this->detectedSimilarity;
    }

    public function getSolution(): ?AssignmentSolution
    {
        return $this->solution;
    }

    public function getSolutionFile(): ?SolutionFile
    {
        return $this->solutionFile;
    }

    public function getFileEntry(): string
    {
        return $this->fileEntry;
    }

    public function getFragments(): array
    {
        return $this->fragments ? json_decode($this->fragments, true) : [];
    }

    public function setFragments(array $fragments): void
    {
        $fragmentsHelper = new SimilarityFragments();
        $fragmentsHelper->load($fragments); // throws if validation fails
        $this->fragments = json_encode($fragmentsHelper);
    }
}
