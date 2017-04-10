<?php
namespace App\Model\Repository;

use App\Model\Entity\AdditionalExerciseFile;
use Kdyby\Doctrine\EntityManager;

class AdditionalExerciseFiles extends BaseRepository
{
  public function __construct(EntityManager $em)
  {
    parent::__construct($em, AdditionalExerciseFile::class);
  }
}