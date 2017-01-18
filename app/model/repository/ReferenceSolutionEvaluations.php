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
    parent::__construct($em, ReferenceSolutionEvaluation::class);
  }

  /**
   * Find all the reference solutions' evaluations for a given exercise, runtime environment and hardware group.
   * @param Exercise            $exercise         The exercise
   * @param RuntimeEnvironment  $environment      The runtime environment
   * @param string              $hardwareGroupId  Hardware group
   * @return array
   */
  public function find(Exercise $exercise, RuntimeEnvironment $environment, string $hardwareGroupId) {
    $eva = ReferenceSolutionEvaluation::class;
    $query = $this->em->createQuery(
      "SELECT eva FROM $eva eva INNER JOIN eva.referenceSolution ref " .
      "  INNER JOIN ref.solution sol INNER JOIN sol.runtimeConfig rc " .
      "WHERE IDENTITY(ref.exercise) = :exercise " .
      "  AND IDENTITY(rc.runtimeEnvironment) = :environment " .
      "  AND eva.hwGroup = :hwGroup"
    );

    $query->setParameters([
      "exercise" => $exercise->getId(),
      "environment" => $environment->getId(),
      "hwGroup" => $hardwareGroupId
    ]);

    return $query->getResult();
  }

}
