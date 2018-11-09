<?php

namespace App\Model\Repository;

use App\Model\Entity\Notification;
use Kdyby\Doctrine\EntityManager;


class Notifications extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Notification::class);
  }
}
