<?php

namespace App\Model\Repository;

use App\Model\Entity\Exercise;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\RuntimeEnvironment;

use Kdyby\Doctrine\EntityManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;

use Nette;
use DateTime;

class ReferenceSolutionSubmissions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ReferenceSolutionSubmission::class);
  }

}
