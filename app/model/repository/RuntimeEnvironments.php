<?php

namespace App\Model\Repository;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\RuntimeEnvironment;

use App\Exceptions\NotFoundException;

class RuntimeEnvironments extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, RuntimeEnvironment::CLASS);
  }

  /**
   * Detect runtime environment based on the extensions of the submitted files.
   * @param UploadedFile[]  $files        The files
   * @return RuntimeEnvironment
   * @throws NotFoundException
   */
  public function detectOrThrow(array $files): RuntimeEnvironment {
    // @todo: choose one runtime environment based on the filenames (extensions) of submitted files.
    // - if there are multiple candidates (user submitted files with different extensions) - throw an exception. 
    throw new NotFoundException("Cannot detect runtime environment for the submitted files.");
  }


}
