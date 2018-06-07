<?php

namespace App\Model\Repository;

use App\Model\Entity\ShadowAssignmentEvaluation;
use Kdyby\Doctrine\EntityManager;

/**
 * @method ShadowAssignmentEvaluation findOrThrow($id)
 */
class ShadowAssignmentEvaluations extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ShadowAssignmentEvaluation::class);
  }

}
