<?php

namespace App\Model\Repository;

use App\Model\Entity\Group;
use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\UploadedFile;

class UploadedFiles extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, UploadedFile::CLASS);
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
    if ($file->solution === NULL) {
      return NULL;
    }

    $query = $this->em->createQuery("
      SELECT sub
      FROM App\Model\Entity\Submission sub
      WHERE IDENTITY(sub.solution) = :solutionId
    ");

    $query->setParameters([
      'solutionId' => $file->solution->id
    ]);

    $result = $query->getOneOrNullResult();

    if ($result === NULL) {
      return NULL;
    }

    return $result->assignment->group;
  }
}
