<?php
namespace App\Helpers;
use Nette;
use Nette\Utils\Arrays;

class UploadsConfig {
  use Nette\SmartObject;

  /**
   * @var string The longest time a file can exist without being used
   */
  protected $removalThreshold;

  /**
   * @var int A limit for sizes of uploaded file previews (in bytes)
   */
  protected $maxPreviewSize;

  /**
   * UploadsConfig constructor.
   * @param array $config
   * @internal param string $removalThreshold
   */
  public function __construct(array $config) {
    $this->removalThreshold = Arrays::get($config, "removalThreshold", "1 day");
    $this->maxPreviewSize = Arrays::get($config, "maxPreviewSize", 65536);
  }

  public function getMaxPreviewSize(): int {
    return $this->maxPreviewSize;
  }

  public function getRemovalThreshold(): string {
    return $this->removalThreshold;
  }
}
