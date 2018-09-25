<?php

namespace App\Model\Repository;

use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\Group;
use App\Model\Entity\GroupMembership;
use App\Model\Entity\User;
use App\Model\Helpers\PaginationDbHelper;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Doctrine\EntityManager;

/**
 * @method Group findOrThrow($id)
 */
class Groups extends BaseSoftDeleteRepository  {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Group::class);
  }

  /**
   * Find all groups belonging to specified instance.
   * @param Instance $instance
   * @return array
   */
  public function findAllByInstance(Instance $instance) {
    return $this->findBy([ 'instance' => $instance->getId() ]);
  }

  public function findUnarchived() {
    return $this->repository->findBy([
      $this->softDeleteColumn => null,
      "archivationDate" => null
    ]);
  }

  /**
   * @return Group[]
   */
  public function findArchived() {
    $qb = $this->em->createQueryBuilder();
    $qb->select("g")
      ->from(Group::class, "g")
      ->where($qb->expr()->isNull("g." . $this->softDeleteColumn))
      ->andWhere($qb->expr()->isNotNull("archivationDate"));
    return $qb->getQuery()->getArrayResult();
  }

  /**
   * Check if the name of the group is free within group and instance.
   */
  public function findByName(string $locale, string $name, Instance $instance, ?Group $parentGroup) {
    $textsQb = $this->em->createQueryBuilder();
    $textsQb->addSelect("l")->from(LocalizedGroup::class, "l");
    $textsQb->where($textsQb->expr()->eq("l.name", ":name"));
    $textsQb->andWhere($textsQb->expr()->eq("l.locale", ":locale"));

    $textsQb->setParameters([
      "name" => $name,
      "locale" => $locale
    ]);

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
    } else {
      $groupsQb->andWhere($groupsQb->expr()->isNull("p.id"));
    }

    $groupsQb->setParameters($parameters);

    return $groupsQb->getQuery()->getResult();
  }

  /**
   * Returns a set of groups based on given filtering conditions.
   * @param User|null $user If not null, only groups in which this user is involved (has active membership) in
   *  are returned. Otherwise, all groups are returned.
   * @param string|null $instanceId ID of an instance to which the groups belongs to.
   * @param string|null $search Search query.
   * @param bool|null $archived Whether to include archived groups.
   * @param bool|null $onlyArchived Automatically implies $archived flag and returns only archived groups.
   * @param int|null $archivedAgeLimit Restricting included archived groups by how long they have been archived.
   *  Groups archived before $archivedAgeLimit days (or more) are not included in the result.
   */
  public function findFiltered(User $user = null, string $instanceId = null, string $search = null,
    bool $archived = false, bool $onlyArchived = false, int $archivedAgeLimit = null)
  {
    $qb = $this->createQueryBuilder('g'); // takes care of softdelete cases

    // Filter by instance ID...
    $instanceId = trim($instanceId);
    if ($instanceId) {
      $qb->andWhere(':instanceId = g.instance')->setParameter('instanceId', $instanceId);
    }

    // Filter by user membership...
    if ($user) {
      $sub = $qb->getEntityManager()->createQueryBuilder()->select("gm")->from(GroupMembership::class, "gm");
      $sub->andWhere($sub->expr()->eq('gm.user', $sub->expr()->literal($user->getId())));
      $sub->andWhere($sub->expr()->eq('gm.status', $sub->expr()->literal(GroupMembership::STATUS_ACTIVE)));
      $sub->andWhere($sub->expr()->eq('gm.group', 'g'));
      $qb->andWhere($qb->expr()->exists($sub->getDQL()));
    }

    // Filter by search string...
    if ($search) {
      $paginationDbHelper = new PaginationDbHelper([], [ 'name' ], LocalizedGroup::class);
      $paginationDbHelper->applySearchFilter($qb, $search);
    }

    // Filtering by archived flags...
    $archived = $archived || $onlyArchived;
    if (!$archived) {
      $qb->andWhere($qb->expr()->isNull('g.archivationDate'));
    } else if ($onlyArchived) {
      $qb->andWhere($qb->expr()->isNotNull('g.archivationDate'));
    }

    if ($archived && $archivedAgeLimit) {
      $minDate = new \DateTime();
      $minDate->sub(\DateInterval::createFromDateString("$archivedAgeLimit days"));
      $qb->andWhere('g.archivationDate >= :minDate')->setParameter('minDate', $minDate);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * Gets an initial set of groups and produces a set of groups which have the
   * original set as a subset and every group has its parent in the set as well.
   * @param Group[] $groups Initial set of groups
   */
  public function groupsAncestralClosure(array $groups)
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
   * @param string[] $groupIds Initial set of group IDs
   */
  public function groupsIdsAncestralClosure(array $groupIds)
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
}
