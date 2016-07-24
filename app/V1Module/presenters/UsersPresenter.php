<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Users;

class UsersPresenter extends BasePresenter {

  /**
   * @GET
   */
  public function actionDefault() {
    $users = $this->users->findAll();
    $this->sendJson($users);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendJson($user);
  }

  /**
   * @GET
   */
  public function actionGroups(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendJson($user->getGroups()->toArray());
  }

  /**
   * @GET
   */
  public function actionExercises(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendJson($user->getUsersExercises()->toArray());
  }

}
