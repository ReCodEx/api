<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use Nette\Http\FileUpload;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use App\Model\Entity\RuntimeEnvironment;
use App\Exceptions\NotFoundException;
use App\Exceptions\ApiException;

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
      return $this->getFileExtension($file);
    }, $files);

    // go through all environments and found suitable ones
    $foundEnvironments = array();
    foreach ($this->findAll() as $runtimeEnvironment) {
      $runtimeExtensions = $this->parseYaml($runtimeEnvironment->getExtensions());

      // go through all given extensions and match them against runtime environment ones
      $allSuitable = true;
      foreach ($extensions as $ext) {
        if (!in_array($ext, $runtimeExtensions)) {
          $allSuitable = false;
          break;
        }
      }

      // if all extensions belong to this runtime environment save it
      if ($allSuitable) {
        $foundEnvironments[] = $runtimeEnvironment;
      }
    }

    // environment has to be only one to avoid ambiguity matches
    if (count($foundEnvironments) == 0 || count($foundEnvironments) > 1) {
      throw new NotFoundException("Cannot detect runtime environment for the submitted files.");
    }

    return $foundEnvironments[0];
  }

  /**
   * Parse given string into yaml structure and return it.
   * @param string $content
   * @return array decoded YAML
   * @throws ApiException in case of parsing error
   */
  private function parseYaml(string $content) {
    try {
      $parsedConfig = Yaml::parse($content);
    } catch (ParseException $e) {
      throw new ApiException("Yaml cannot be parsed: " . $e->getMessage());
    }

    return $parsedConfig;
  }

  /**
   * Extract extension from given file and return it.
   * @param FileUpload $fileUpload
   * @return string extension
   * @throws ApiException if file does not have an extension
   */
  private function getFileExtension(FileUpload $fileUpload): string {
    $ext = pathinfo($fileUpload->getSanitizedName(), PATHINFO_EXTENSION);
    if ($ext === NULL) {
      throw new ApiException("File does not containt extension: " . $fileUpload->getSanitizedName());
    }

    return $ext;
  }

}
