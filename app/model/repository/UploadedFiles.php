<?php

namespace App\Model\Repository;

use App\Model\Entity\Group;
use App\Model\Entity\SolutionFile;
use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\UploadedFile;

/**
 * @method UploadedFile findOrThrow($id)
 */
class UploadedFiles extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, UploadedFile::class);
  }

  public function findAllById($ids) {
    return $this->findBy([ "id" => $ids ]);
  }

  /**
   * If given file belongs to an exercise assignment, find the group where the exercise was assigned
   * @param UploadedFile $file
   * @return Group|null
   */
  public function findGroupForSolutionFile(UploadedFile $file)
  {
    if (!($file instanceof SolutionFile)) {
      return NULL;
    }

    $query = $this->em->createQuery("
      SELECT sub
      FROM App\Model\Entity\AssignmentSolution sub
      WHERE IDENTITY(sub.solution) = :solutionId
    ");

    $query->setParameters([
      'solutionId' => $file->getSolution()->getId()
    ]);

    $result = $query->getResult();
    if (count($result) === 0) {
      return NULL;
    }

    return current($result)->getAssignment()->getGroup();
  }

  /**
   * If given file belongs to an exercise, find groups to which exercise belongs to.
   * @param UploadedFile $file
   * @return Group[]
   */
  public function findGroupsForReferenceSolutionFile(UploadedFile $file)
  {
    if (!($file instanceof SolutionFile)) {
      return [];
    }

    $query = $this->em->createQuery("
      SELECT ref
      FROM App\Model\Entity\ReferenceExerciseSolution ref
      WHERE IDENTITY(ref.solution) = :solutionId
    ");

    $query->setParameters([
      'solutionId' => $file->getSolution()->getId()
    ]);

    $result = $query->getResult();
    if (count($result) === 0) {
      return [];
    }

    return current($result)->getExercise()->getGroups()->toArray();
  }

  /**
   * Find uploaded files that are too old and not assigned to an Exercise or Solution
   * @param DateTime $now Current date
   * @param string $threshold Maximum allowed age of uploaded files
   *                          (in a form acceptable by DateTime::modify after prefixing with a "-" sign)
   * @return UploadedFile[]
   */
  public function findUnused(DateTime $now, $threshold)
  {
    $thresholdDate = clone $now;
    $thresholdDate->modify("-" . $threshold);

    $plainFilesQuery = $this->em->createQuery("
      SELECT f
      FROM App\Model\Entity\UploadedFile f
      WHERE f INSTANCE OF App\Model\Entity\UploadedFile
      AND f.uploadedAt < :threshold
    ");

    $plainFilesQuery->setParameters([
      "threshold" => $thresholdDate
    ]);

    $supplementaryFilesQuery = $this->em->createQuery("
      SELECT f
      FROM App\Model\Entity\SupplementaryExerciseFile f
      WHERE f.exercises IS EMPTY
      AND f.uploadedAt < :threshold
    ");

    $supplementaryFilesQuery->setParameters([
      "threshold" => $thresholdDate
    ]);

    $additionalFilesQuery = $this->em->createQuery("
      SELECT f
      FROM App\Model\Entity\AdditionalExerciseFile f
      WHERE f.exercises IS EMPTY
      AND f.uploadedAt < :threshold
    ");

    $additionalFilesQuery->setParameters([
      "threshold" => $thresholdDate
    ]);

    return $plainFilesQuery->getResult() + $supplementaryFilesQuery->getResult() + $additionalFilesQuery->getResult();
  }
}
