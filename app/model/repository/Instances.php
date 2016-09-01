<?php

namespace App\Model\Repository;

class Instances extends BaseRepository {
    protected $entityName = "Instance";

    public function remove($instance, $autoFlush = TRUE) {
      foreach ($instance->licences as $licence) {
        $this->em->remove($licence);
      }
      parent::remove($instance, TRUE);
    }
}
