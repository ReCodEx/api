<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Groups;

class GroupsPresenter extends BasePresenter {

  /** @var Groups */
  private $groups;

  /**
   * @param Groups $groups  Groups repository
   */
  public function __construct(Groups $groups) {
    $this->groups = $groups;
  }

  protected function findGroupOrThrow($id) {
    $group = $this->groups->get($id);
    if (!$group) {
      // @todo report 404 error
      throw new Exception;
    }

    return $group;
  }

  public function actionGetAll() {
    $groups = $this->groups->findAll();
    $this->sendJson($groups);
  }

  public function actionDetail(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendJson($group);
  }

  public function actionMembers(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendJson([
      'supervisors' => $group->getSupervisors()->toArray(),
      'students' => $group->getStudents()->toArray()
    ]);
  }

  public function actionAssignments(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendJson($group->getAssignments()->toArray());
  }

}
