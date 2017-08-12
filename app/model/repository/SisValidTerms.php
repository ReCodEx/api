<?php
namespace App\Model\Repository;

use App\Model\Entity\SisValidTerm;
use Kdyby\Doctrine\EntityManager;

class SisValidTerms extends BaseRepository {
  public function __construct(EntityManager $em) {
    parent::__construct($em, SisValidTerm::class);
  }

  public function isValid($year, $term) {
    return $this->findOneBy([
      "year" => $year,
      "term" => $term
    ]);
  }
}