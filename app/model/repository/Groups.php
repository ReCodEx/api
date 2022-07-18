<?php

namespace App\Model\Repository;

use DateTime;
use DateInterval;
use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\Group;
use App\Model\Entity\GroupMembership;
use App\Model\Entity\User;
use App\Model\Helpers\PaginationDbHelper;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseSoftDeleteRepository<Group>
 */
class Groups extends BaseSoftDeleteRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Group::class);
    }

    /**
     * Find all groups belonging to specified instance.
     * @param Instance $instance
     * @return Group[]
     */
    public function findAllByInstance(Instance $instance): array
    {
        return $this->findBy(['instance' => $instance->getId()]);
    }

    /**
     * @return Group[]
     */
    public function findUnarchived(): array
    {
        return $this->repository->findBy(
            [
                $this->softDeleteColumn => null,
                "archivedAt" => null
            ]
        );
    }

    /**
     * @return Group[]
     */
    public function findArchived(): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select("g")
            ->from(Group::class, "g")
            ->where($qb->expr()->isNull("g." . $this->softDeleteColumn))
            ->andWhere($qb->expr()->isNotNull("archivedAt"));
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Check if the name of the group is free within group and instance.
     * @return Group[]
     */
    public function findByName(string $locale, string $name, Instance $instance, ?Group $parentGroup = null): array
    {
        $textsQb = $this->em->createQueryBuilder();
        $textsQb->addSelect("l")->from(LocalizedGroup::class, "l");
        $textsQb->where($textsQb->expr()->eq("l.name", ":name"));
        $textsQb->andWhere($textsQb->expr()->eq("l.locale", ":locale"));

        $textsQb->setParameters(
            [
                "name" => $name,
                "locale" => $locale
            ]
        );

        $texts = $textsQb->getQuery()->getResult();

        if (count($texts) === 0) {
            return [];
        }

        $groupsQb = $this->em->createQueryBuilder();
        $groupsQb->addSelect("g")->from(Group::class, "g")
            ->innerJoin("g.instance", "i")
            ->leftJoin("g.parentGroup", "p");

        $criteria = [];
        $parameters = [
            "instanceId" => $instance->getId()
        ];

        /** @var LocalizedGroup $text */
        foreach ($texts as $i => $text) {
            $criteria[] = $groupsQb->expr()->isMemberOf(":text_" . $i, "g.localizedTexts");
            $parameters["text_" . $i] = $text->getId();
        }

        $groupsQb->andWhere($groupsQb->expr()->eq("i.id", ":instanceId"));
        $groupsQb->andWhere($groupsQb->expr()->isNull("g.deletedAt"));
        $groupsQb->andWhere($groupsQb->expr()->orX(...$criteria));

        if ($parentGroup) {
            $criteria = [$groupsQb->expr()->eq("p.id", ":parentGroupId")];
            $parameters["parentGroupId"] = $parentGroup->getId();

            if ($parentGroup === $instance->getRootGroup()) {
                $criteria[] = $groupsQb->expr()->isNull("p.id");
            }

            $groupsQb->andWhere($groupsQb->expr()->orX(...$criteria));
        }

        $groupsQb->setParameters($parameters);

        return $groupsQb->getQuery()->getResult();
    }

    /**
     * Fetch all groups in which the given user has membership (all relations except admin).
     * @param User $user The user whos memberships are considered.
     * @param array $allowed List of allowed membership types (empty list = no restrictions)
     * @param array $denied List of denied membership types (empty list = no restrictions)
     * @return Group[] Array indexed by group IDs.
     */
    private function findGroupsByMembership(User $user, array $allowed = [], array $denied = []): array
    {
        $qb = $this->createQueryBuilder('g'); // takes care of softdelete cases
        $sub = $qb->getEntityManager()->createQueryBuilder()->select("gm")->from(GroupMembership::class, "gm");
        $sub->andWhere($sub->expr()->eq('gm.group', 'g'));
        $sub->andWhere($sub->expr()->eq('gm.user', $sub->expr()->literal($user->getId())));

        // filter membership types
        if ($allowed) {
            $sub->andWhere($sub->expr()->in('gm.type', $allowed));
        }
        if ($denied) {
            $sub->andWhere($sub->expr()->notIn('gm.type', $denied));
        }

        $qb->andWhere($qb->expr()->exists($sub->getDQL()));

        $res = [];
        foreach ($qb->getQuery()->getResult() as $group) {
            $res[$group->getId()] = $group;
        }
        return $res;
    }

    /**
     * Find immediate children of all parent groups on a given list.
     * This can be used to iteratively get whole subtree of groups quite efficiently.
     * @param string[] $parentsIds list of IDs of all parent groups
     * @return Group[]
     */
    private function findAllChildrenOf(array $parentsIds): array
    {
        if (!$parentsIds) {
            return [];
        }
        $qb = $this->createQueryBuilder('g'); // takes care of softdelete cases
        $qb->andWhere($qb->expr()->in("g.parentGroup", $parentsIds));
        return $qb->getQuery()->getResult();
    }

    /**
     * Find all groups that are supervised by a user (as supervisor, admin, or admin inherently).
     * @param User $user The supervisor/admin
     * @return Group[] Array indexed by group IDs.
     */
    public function findSupervisedGroupsIds(User $user): array
    {
        $res = [];

        // Add groups where user is admin or inherits admin privileges.
        $admined = $this->findGroupsByMembership($user, [ GroupMembership::TYPE_ADMIN ]);
        while ($admined) {
            $parents = []; // groups that become parents of next iteration
            foreach ($admined as $group) {
                if (!array_key_exists($group->getId(), $res)) {
                    $res[$group->getId()] = $group;
                    $parents[] = $group->getId();
                }
            }
            $admined = $this->findAllChildrenOf($parents); // load all children of given parents at once
        }

        // Add groups where user is direct supervisor.
        $supervised = $this->findGroupsByMembership($user, [ GroupMembership::TYPE_SUPERVISOR ]);
        foreach ($supervised as $group) {
            $res[$group->getId()] = $group;
        }

        return $res;
    }


    /**
     * Filter list of groups so that only groups affiliated to given user
     * (by direct membership or by admin rights) and public groups remain in the result.
     * @param User $user User whos affiliation is considered.
     * @param Group[] $groups List of groups to be filtered.
     * @return Group[]
     */
    private function filterGroupsByUser(User $user, array $groups): array
    {
        $memberOf = $this->findGroupsByMembership($user, [], [ GroupMembership::TYPE_ADMIN ]); // not admins
        $adminOf = $this->findGroupsByMembership($user, [ GroupMembership::TYPE_ADMIN ]); // only admins

        return array_filter(
            $groups,
            function (Group $group) use ($memberOf, &$adminOf) {
                $id = $group->getId();

                // The group is directly associated with the user...
                if ($group->isPublic() || array_key_exists($id, $memberOf) || array_key_exists($id, $adminOf)) {
                    return true;
                }

                // Or the user has admin rights to one of the ancestors...
                $parent = $group->getParentGroup();
                while ($parent) {
                    if (array_key_exists($parent->getId(), $adminOf)) {
                        // marker that this group has inherited admin rights (performance optimization)
                        $adminOf[$id] = true;
                        return true;
                    }
                    $parent = $parent->getParentGroup();
                }

                return false; // this group should be filtered out
            }
        );
    }

    /**
     * Returns a set of groups based on given filtering conditions.
     * @param User|null $user If not null, only groups in which this user is involved (has active membership) in
     *  are returned. Otherwise, all groups are returned.
     * @param string|null $instanceId ID of an instance to which the groups belongs to.
     * @param string|null $search Search query.
     * @param bool $archived Whether to include archived groups.
     * @param bool $onlyArchived Automatically implies $archived flag and returns only archived groups.
     * @return Group[]
     */
    public function findFiltered(
        User $user = null,
        string $instanceId = null,
        string $search = null,
        bool $archived = false,
        bool $onlyArchived = false
    ): array {
        $qb = $this->createQueryBuilder('g'); // takes care of softdelete cases

        // Filter by instance ID...
        $instanceId = trim($instanceId ?? "");
        if ($instanceId) {
            $qb->andWhere(':instanceId = g.instance')->setParameter('instanceId', $instanceId);
        }

        if ($onlyArchived) {
            // this must go first, since onlyArchived overrides archived flag
            $qb->andWhere('g.archivedAt IS NOT NULL');
        } elseif (!$archived) {
            $qb->andWhere('g.archivedAt IS NULL');
        }

        // Filter by search string...
        if ($search) {
            $paginationDbHelper = new PaginationDbHelper([], ['name'], LocalizedGroup::class);
            $paginationDbHelper->applySearchFilter($qb, $search);
        }

        $groups = $qb->getQuery()->getResult();

        // Filter by user membership...
        if ($user) {
            $groups = $this->filterGroupsByUser($user, $groups);
        }

        return array_values($groups);
    }

    /**
     * Gets an initial set of groups and produces a set of groups which have the
     * original set as a subset and every group has its parent in the set as well.
     * @param iterable $groups Initial set of groups
     * @return Group[]
     */
    public function groupsAncestralClosure(iterable $groups): array
    {
        $res = [];
        foreach ($groups as $group) {
            $groupId = $group->getId();
            if (array_key_exists($groupId, $res)) { // @neloop made me do that
                continue;
            }
            $res[$groupId] = $group;
            foreach ($group->getParentGroupsIds() as $id) {
                if (!array_key_exists($id, $res)) {
                    $res[$id] = $this->findOrThrow($id);
                }
            }
        }
        return array_values($res);
    }

    /**
     * Gets a set of group IDs and produces a set of group IDs which have the
     * original set as a subset and every group has its parent in the set as well.
     * @param iterable $groupIds Initial set of group IDs
     * @return string[]
     */
    public function groupsIdsAncestralClosure(iterable $groupIds): array
    {
        $res = [];
        foreach ($groupIds as $groupId) {
            if (array_key_exists($groupId, $res)) { // @neloop made me do that
                continue;
            }
            $res[$groupId] = true;
            $group = $this->findOrThrow($groupId);
            foreach ($group->getParentGroupsIds() as $id) {
                $res[$id] = true;
            }
        }
        return array_keys($res);
    }

    /**
     * Get total number of archived groups.
     */
    public function getArchivedCount(): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.archivedAt IS NOT NULL');
        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Retrieve all groups that where at least one exercise resides.
     * @param Exercises $exercises repository (used for subselect)
     * @param bool $onlyIds whether to retrieve only groupIDs or all entities
     * @param bool $archived if true, archived groups are also returned
     * @return array (either string[] if onlyIds is set, or Group[] otherwise)
     */
    public function findExerciseGroupsOfResidence(
        Exercises $exercises,
        bool $onlyIds = false,
        bool $archived = false
    ): array {
        $qb = $this->createQueryBuilder('g');
        if ($onlyIds) {
            $qb->select('g.id');
        }

        // a not-deleted exercise must exist and be attached to the group
        $sub = $exercises->createQueryBuilder('e');
        $sub->where($sub->expr()->isMemberOf("e", "g.exercises"));
        $qb->where($qb->expr()->exists($sub->getDQL()));

        if (!$archived) {
            $qb->andWhere('g.archivedAt IS NULL');
        }

        return array_values($qb->getQuery()->getResult());
    }
}
