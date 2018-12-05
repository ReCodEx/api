<?php

namespace App\Model\Repository;

use App\Model\Entity\ExerciseTest;
use Kdyby\Doctrine\EntityManager;
/*
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Query;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Helpers\Pagination;
use App\Model\Helpers\PaginationDbHelper;
use App\Exceptions\InvalidArgumentException;
*/

/**
 * @method ExerciseTest findOrThrow($id)
 */
class ExerciseTests extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ExerciseTest::class);
  }
}
