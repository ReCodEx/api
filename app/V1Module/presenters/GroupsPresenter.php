<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Groups;
use App\Exception\BadRequestException;
use App\Exception\ForbiddenRequestException;
use App\Exception\NotFoundException;

/**
 * @LoggedIn
 */
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
      throw new NotFoundException("Group $id");
    }

    return $group;
  }

  /**
   * @GET
   * @UserIsAllowed(groups="view-all")
   */
  public function actionDefault() {
    $groups = $this->groups->findAll();
    $this->sendSuccessResponse($groups);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse($group);
  }

  /**
   * @GET
   */
  public function actionMembers(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse([
      "supervisors" => $group->getSupervisors()->toArray(),
      "students" => $group->getStudents()->toArray()
    ]);
  }

  /**
   * @GET
   */
  public function actionSupervisors(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse($group->getSupervisors()->toArray());
  }

  /**
   * @GET
   */
  public function actionStudents(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse($group->getStudents()->toArray());
  }

  /**
   * @GET
   */
  public function actionAssignments(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse($group->getAssignments()->toArray());
  }

  /** @GET */
  public function actionStudentsStats(string $groupId, string $userId) {
    $user = $this->findUserOrThrow($userId);
    $currentUser = $this->findUserOrThrow('me');
    $group = $this->findGroupOrThrow($groupId);

    if ($user->getId() !== $this->user->id
      && !$group->isSupervisorOf($currentUser)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You cannot view these stats.");
    }

    if ($group->isStudentOf($user) === FALSE) {
      throw new BadRequestException("User $userId is not student of $groupId");
    }

    $this->sendSuccessResponse($group->getStudentsStats($user));
  }

}
