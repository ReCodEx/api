<?php

namespace App\Model\Repository;

use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Group;

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
   * Get a set of group IDs and produce a set of group IDs which have the
   * original set as a subset and every group has its parent in the set as well.
   * @param string[] $groupIds Initial set of group IDs
   */
  public function groupIdsAncestralClosure(array $groupIds)
  {
    $res = [];
    foreach ($groupIds as $groupId) {
      if (array_key_exists($groupId, $res)) continue;
      $res[$groupId] = true;
      $group = $this->findOrThrow($groupId);
      foreach ($group->getParentGroupsIds() as $id) {
        $res[$id] = true;
      }
    }
    return array_keys($res);
  }
}
