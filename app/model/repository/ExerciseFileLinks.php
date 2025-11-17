<?php

namespace App\Model\Repository;

use App\Model\Entity\ExerciseFileLink;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ExerciseFileLink>
 */
class ExerciseFileLinks extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ExerciseFileLink::class);
    }

    /**
     * Load an associative array [ key => link-ID ] for all file links of the given exercise.
     * @param string $exerciseId
     * @return array<string, string>
     */
    public function getLinksMapForExercise(string $exerciseId): array
    {
        $links = $this->findBy(['exercise' => $exerciseId]);
        $result = [];
        foreach ($links as $link) {
            /** @var ExerciseFileLink $link */
            $result[$link->getKey()] = $link->getId();
        }
        return $result;
    }

    /**
     * Load an associative array [ key => link-ID ] for all file links of the given assignment.
     * @param string $assignmentId
     * @return array<string, string>
     */
    public function getLinksMapForAssignment(string $assignmentId): array
    {
        $links = $this->findBy(['assignment' => $assignmentId]);
        $result = [];
        foreach ($links as $link) {
            /** @var ExerciseFileLink $link */
            $result[$link->getKey()] = $link->getId();
        }
        return $result;
    }
}
