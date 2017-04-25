<?php
namespace App\Helpers;
use Nette;
use Nette\Utils\Arrays;

class UploadsConfig extends Nette\Object {
  /**
   * @var string The longest time a file can exist without being used
   */
  protected $removalThreshold;

  /**
   * UploadsConfig constructor.
   * @param array $config
   * @internal param string $removalThreshold
   */
  public function __construct(array $config) {
    $this->removalThreshold = Arrays::get($config, "removalThreshold", "1 day");
  }

  public function getRemovalThreshold(): string {
    return $this->removalThreshold;
  }
}
