<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Users;

class UsersPresenter extends BasePresenter {

  public function actionGetAll() {
    $users = $this->users->findAll();
    $this->sendJson($users);
  }

  public function actionDetail(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendJson($user);
  }

  public function actionGroups(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendJson($user->getGroups()->toArray());
  }

  public function actionExercises(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendJson($user->getUsersExercises()->toArray());
  }

}
