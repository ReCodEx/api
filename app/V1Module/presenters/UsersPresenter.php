<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Users;

class UsersPresenter extends BasePresenter {

  /**
   * @GET
   */
  public function actionDefault() {
    $users = $this->users->findAll();
    $this->sendSuccessResponse($users);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendSuccessResponse($user);
  }

  /**
   * @GET
   */
  public function actionGroups(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendSuccessResponse([
      'supervisor' => $user->getGroupsAsSupervisor()->toArray(),
      'student' => $user->getGroupsAsStudent()->toArray()
    ]);
  }

  /**
   * @GET
   */
  public function actionExercises(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendSuccessResponse($user->getUsersExercises()->toArray());
  }

}
