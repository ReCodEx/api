<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Groups;
use App\Exception\BadRequestException;
use App\Exception\NotFoundException;

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
      throw new NotFoundException;
    }

    return $group;
  }

  /**
   * @GET
   */
  public function actionDefault() {
    $groups = $this->groups->findAll();
    $this->sendJson($groups);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendJson($group);
  }

  /**
   * @GET
   */
  public function actionMembers(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendJson([
      'supervisors' => $group->getSupervisors()->toArray(),
      'students' => $group->getStudents()->toArray()
    ]);
  }

  /**
   * @GET
   */
  public function actionAssignments(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendJson($group->getAssignments()->toArray());
  }

}
