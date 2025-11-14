<?php

namespace App\Model\Repository;

use App\Helpers\GroupBindings\IGroupBindingProvider;
use App\Model\Entity\Group;
use App\Model\Entity\SisGroupBinding;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<SisGroupBinding>
 * @deprecated
 */
class SisGroupBindings extends BaseRepository implements IGroupBindingProvider
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, SisGroupBinding::class);
    }

    /**
     * @param string|null $code
     * @return SisGroupBinding[]
     */
    public function findByCode($code): array
    {
        $qb = $this->createQueryBuilder("sis");
        $qb->leftJoin("sis.group", "gr")
            ->andWhere($qb->expr()->eq("sis.code", ":code"))
            ->andWhere($qb->expr()->isNull("gr.deletedAt"))
            ->setParameter("code", $code);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Group $group
     * @param string|null $code
     * @return SisGroupBinding|null
     */
    public function findByGroupAndCode(Group $group, $code): ?SisGroupBinding
    {
        return $this->findOneBy(
            [
                'code' => $code,
                'group' => $group
            ]
        );
    }

    /**
     * @return string a unique identifier of the type of the binding
     */
    public function getGroupBindingIdentifier(): string
    {
        return "sis";
    }

    /**
     * @param Group $group
     * @return SisGroupBinding[] all entities bound to the group (they must have __toString() implemented)
     */
    public function findGroupBindings(Group $group): array
    {
        return $this->findBy(
            [
                "group" => $group
            ]
        );
    }
}
