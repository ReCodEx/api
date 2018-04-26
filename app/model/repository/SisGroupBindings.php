<?php
namespace App\Model\Repository;

use App\Helpers\GroupBindings\IGroupBindingProvider;
use App\Model\Entity\Group;
use App\Model\Entity\SisGroupBinding;
use Kdyby\Doctrine\EntityManager;

class SisGroupBindings extends BaseRepository implements IGroupBindingProvider {
  public function __construct(EntityManager $em) {
    parent::__construct($em, SisGroupBinding::class);
  }

  /**
   * @param $code
   * @return SisGroupBinding[]
   */
  public function findByCode($code) {
    return $this->findBy([
      'code' => $code,
      'group.deletedAt' => null
    ]);
  }

  /**
   * @param $group
   * @param $code
   * @return SisGroupBinding|null
   */
  public function findByGroupAndCode(Group $group, $code) {
    return $this->findOneBy([
      'code' => $code,
      'group' => $group
    ]);
  }

  /**
   * @return string a unique identifier of the type of the binding
   */
  public function getGroupBindingIdentifier(): string {
    return "sis";
  }

  /**
   * @param Group $group
   * @return array all entities bound to the group (they must have __toString() implemented)
   */
  public function findGroupBindings(Group $group): array {
    return $this->findBy([
      "group" => $group
    ]);
  }
}
