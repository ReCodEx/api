<?php

namespace App\Model\Repository;

use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseTag;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ExerciseTag>
 */
class ExerciseTags extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ExerciseTag::class);
    }

    /**
     * Verify whether given string can be used as a tag name.
     * @param string $name
     * @return bool
     */
    public function verifyTagName(string $name): bool
    {
        return preg_match('/^[-a-zA-Z0-9_]{1,32}$/', $name);
    }

    public function findByNameAndExercise(string $name, Exercise $exercise): ?ExerciseTag
    {
        return $this->findOneBy(
            [
                'name' => $name,
                'exercise' => $exercise
            ]
        );
    }

    public function tagExists(string $name): bool
    {
        return $this->findOneBy(['name' => $name]) !== null;
    }

    /**
     * Get all exercise tag names distinct by their names.
     * @return string[]
     */
    public function findAllDistinctNames(): array
    {
        $qb = $this->createQueryBuilder('et')->select('et.name')->distinct();
        $result = $qb->getQuery()->getResult();
        return array_column($result, 'name');
    }

    /**
     * Computes, how many times is each tag used.
     * @return array Array indexed by tag names, values are numbers of adjacent exercises.
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('et')->select(['et.name', 'COUNT(et.id) AS cnt'])->groupBy('et.name');
        $result = $qb->getQuery()->getScalarResult();
        return array_column($result, 'cnt', 'name');
    }

    /**
     * Rename tag globally (possibly merge two tags).
     * @param string $oldName Tag to be renamed
     * @param string $newName
     * @return int Number of rows affected
     */
    public function renameTag(string $oldName, string $newName): int
    {
        $qb = $this->createQueryBuilder('et');
        $qb->update(ExerciseTag::class, 'et')->set('et.name', ':newName')->where('et.name = :oldName')
            ->setParameter('oldName', $oldName)->setParameter('newName', $newName);
        return $qb->getQuery()->execute();
    }

    /**
     * Remove tag globally (from all exercises).
     * @param string $name
     * @return int Number of rows removed
     */
    public function removeTag(string $name): int
    {
        $qb = $this->createQueryBuilder('et');
        $qb->delete(ExerciseTag::class, 'et')->where('et.name = :name')->setParameter('name', $name);
        return $qb->getQuery()->execute();
    }
}
