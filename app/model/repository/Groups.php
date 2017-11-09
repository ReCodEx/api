<?php

namespace App\Model\Repository;

use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;
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

  /**
   * Check if the name of the group is free within group and instance.
   */
  public function findByName($locale, $name, Instance $instance, ?Group $parentGroup = NULL) {
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

    /** @var LocalizedGroup $text */
    foreach ($texts as $i => $text) {
      $criteria[] = $groupsQb->expr()->isMemberOf($text, "g.localizedTexts");
    }

    $groupsQb->andWhere($groupsQb->expr()->eq("i.id", ":instanceId"));
    $groupsQb->andWhere($groupsQb->expr()->orX(...$criteria));

    $parameters = [
      "instanceId" => $instance->getId()
    ];

    if ($parentGroup) {
      $groupsQb->andWhere($groupsQb->expr()->eq("p.id", ":parentGroupId"));
      $parameters["parentGroupId"] = $parentGroup->getId();
    } else {
      $groupsQb->andWhere($groupsQb->expr()->isNull("p.id"));
    }

    $groupsQb->setParameters($parameters);

    return $groupsQb->getQuery()->getResult();
  }

}
