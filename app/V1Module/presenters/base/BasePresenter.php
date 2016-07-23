<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Users;

class BasePresenter extends \App\Presenters\BasePresenter {
  
  /** @var Users */
  protected $users;

  /**
   * @param Users $users  Users repository
   */
  public function __construct(Users $users) {
    $this->users = $users;
  }

  protected function findUserOrThrow(string $id) {
    if ($id === 'me') {
      if (!$this->user->isLoggedIn()) {
        throw new Exception; // @todo report a 401 error 
      }

      $id = $this->user->id;
    }

    $user = $this->users->get($id);
    if (!$user) {
      // @todo report a 404 error
      throw new Exception;
    }

    return $user;
  }

}
