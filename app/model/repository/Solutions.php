<?php
namespace App\Model\Repository;

use App\Model\Entity\Solution;
use Kdyby\Doctrine\EntityManager;

class Solutions extends BaseRepository
{
  public function __construct(EntityManager $em)
  {
    parent::__construct($em, Solution::class);
  }
}