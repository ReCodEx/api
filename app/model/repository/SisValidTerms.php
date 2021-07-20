<?php

namespace App\Model\Repository;

use App\Model\Entity\SisValidTerm;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<SisValidTerm>
 */
class SisValidTerms extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, SisValidTerm::class);
    }

    /**
     * @param int $year
     * @param int $term
     * @return SisValidTerm|null
     */
    public function isValid($year, $term): ?SisValidTerm
    {
        return $this->findOneBy(
            [
                "year" => $year,
                "term" => $term
            ]
        );
    }

    /**
     * @return SisValidTerm[]
     */
    public function findAll(): array
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
