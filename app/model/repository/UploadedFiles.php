<?php

namespace App\Model\Repository;

use App\Model\Entity\Group;
use App\Model\Entity\SolutionFile;
use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\UploadedFile;

/**
 * @method UploadedFile findOrThrow(string $id)
 */
class UploadedFiles extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, UploadedFile::class);
  }

  public function findAllById($ids) {
    return $this->repository->findBy([ "id" => $ids ]);
  }

  /**
   * If given file belongs to an exercise assignment, find the group where the exercise was assigned
   * @param UploadedFile $file
   * @return Group|null
   */
  public function findGroupForFile(UploadedFile $file)
  {
    if (!($file instanceof SolutionFile)) {
      return NULL;
    }

    $query = $this->em->createQuery("
      SELECT sub
      FROM App\Model\Entity\Submission sub
      WHERE IDENTITY(sub.solution) = :solutionId
    ");

    $query->setParameters([
      'solutionId' => $file->getSolution()->getId()
    ]);

    $result = $query->getOneOrNullResult();

    if ($result === NULL) {
      return NULL;
    }

    return $result->assignment->group;
  }
}
