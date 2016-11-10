<?php

namespace App\Model\Repository;

use App\Model\Entity\RuntimeEnvironment;
use App\Model\Entity\UploadedFile;
use App\Exceptions\NotFoundException;
use Kdyby\Doctrine\EntityManager;

class RuntimeEnvironments extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, RuntimeEnvironment::CLASS);
  }

  /**
   * Detect runtime environment based on the extensions of the submitted files.
   * @param UploadedFile[]  $files        The files
   * @return RuntimeEnvironment
   * @throws NotFoundException if suitable environment was not found
   */
  public function detectOrThrow(array $files): RuntimeEnvironment {
    $extensions = array_map(function ($file) {
      return $file->getFileExtension();
    }, $files);

    // go through all environments and found suitable ones
    $foundEnvironments = array_filter(
      $this->findAll(),
      function($runtimeEnvironment) use ($extensions) {
        // if all extensions belong to this runtime environment save it
        $runtimeExtensions = $runtimeEnvironment->getExtensionsList();
        foreach ($extensions as $ext) {
          if (!in_array($ext, $runtimeExtensions)) {
            return FALSE;
          }
        }
        return TRUE;
      }
    );

    // environment has to be only one to avoid ambiguity matches
    if (count($foundEnvironments) == 0) {
      throw new NotFoundException("There is no suitable runtime environment for the submitted files");
    } else if (count($foundEnvironments) > 1) {
      throw new NotFoundException("There are multiple suitable runtime environments for the submitted files.");
    }

    return current($foundEnvironments);
  }
}
