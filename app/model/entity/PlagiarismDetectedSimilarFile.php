<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Helpers\Plagiarism\SimilarityFragments;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * A record representing a similarity detected in two compared files, which is part of one
 * detected similarity record.
 * The detected similarity holds the reference to the tested file, this entity holds reference
 * to the other file where a similarity was detected.
 * A similarity can comprise multiple text blocks, the details about similar code fragments are
 * encoded in internal JSON record 'fragments' (violating 1NF).
 */
class PlagiarismDetectedSimilarFile implements JsonSerializable
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
     * @ORM\Column(type="string")
     * A submitted file name (of the second solution) where the similarities were detected.
     */
    protected $file;

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
     * @param string $file
     * @param array $fragments
     */
    public function __construct(
        PlagiarismDetectedSimilarity $detectedSimilarity,
        ?AssignmentSolution $solution,
        string $file,
        array $fragments,
    ) {
        $this->detectedSimilarity = $detectedSimilarity;
        $this->solution = $solution;
        $this->file = $file;
        $this->setFragments($fragments);

        $detectedSimilarity->addSimilarFile($this);
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "solutionId" => $this->getSolution() ? $this->getSolution()->getId() : null,
            "file" => $this->getFile(),
            "fragments" => $this->getFragments(),
        ];
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

    public function getFile(): string
    {
        return $this->file;
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
