<?php

namespace App\Model\Repository;

use App\Model\Entity\SisValidTerm;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method SisValidTerm findOrThrow(string $id)
 */
class SisValidTerms extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, SisValidTerm::class);
    }

    public function isValid($year, $term)
    {
        return $this->findOneBy(
            [
                "year" => $year,
                "term" => $term
            ]
        );
    }

    public function findAll()
    {
        return $this->repository->findBy(
            [],
            [
                "year" => "DESC",
                "term" => "DESC",
            ]
        );
    }
}
