<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Helpers\Plagiarism\SimilarityFragments;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={
 *   "detected_similarity_id", "solution_file_id", "file_entry"
 * })})
 * A record representing a similarity detected in two compared files, which is part of one
 * detected similarity record.
 * The detected similarity holds the reference to the tested file, this entity holds reference
 * to the other file where a similarity was detected.
 * A similarity can comprise multiple text blocks, the details about similar code fragments are
 * encoded in internal JSON record 'fragments' (violating 1NF).
 */
class PlagiarismDetectedSimilarFile
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
     * @var PlagiarismDetectedSimilarity
     * @ORM\ManyToOne(targetEntity="PlagiarismDetectedSimilarity")
     * Reference to a detected simularity record to which this file reference contribute.
     */
    protected $detectedSimilarity;

    /**
     * @var AssignmentSolution
     * @ORM\ManyToOne(targetEntity="AssignmentSolution")
     * Another solution in which a similar code was detected.
     * Note: this is nullable in case other sources than assignment solutions are introduced in testing pool.
     */
    protected $solution;

    /**
     * @var SolutionFile
     * @ORM\ManyToOne(targetEntity="SolutionFile")
     * Reference to a solution file where similarities were found.
     * If missing, external sources for comparison were used (and fileEntry is the only identification)
     */
    protected $solutionFile;

    /**
     * @ORM\Column(type="string")
     * Either a relative path within a ZIP file (if the solution file refers to the only ZIP archive),
     * or a reference to external file soure that were used for comparison (preferably an URL).
     */
    protected $fileEntry;

    /**
     * @ORM\Column(type="text", length=65535)
     * JSON encoded structure containing the individual code fragments
     * (as byte-offset references to tested and similar files).
     */
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
