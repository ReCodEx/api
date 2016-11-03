<?php

namespace App\Model\Repository;

use App\Model\Entity\Exercise;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\ReferenceSolutionEvaluation;
use App\Model\Entity\RuntimeEnvironment;

use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;

use Nette;
use DateTime;

class ReferenceSolutionEvaluations extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ReferenceSolutionEvaluation::CLASS);
  }

  /**
   * Find all the reference solutions' evaluations for a given exercise, runtime environment and hardware group.
   * @param Exercise            $exercise       The exercise
   * @param RuntimeEnvironment  $environment    The runtime environment
   * @param HardwareGroup       $hardwareGroup  Hardware group
   * @return ArrayCollection
   */
  public function find(Exercise $exercise, RuntimeEnvironment $environment, HardwareGroup $hardwareGroup) {
    // @todo
    return $this->findAll();
  }

}
