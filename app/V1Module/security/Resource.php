<?php
namespace App\Security;
use Nette;

class Resource implements Nette\Security\IResource {
  private $resourceId;
  private $id;

  public function __construct(string $resourceId, ?string $id) {
    $this->resourceId = $resourceId;
    $this->id = $id;
  }

  /**
   * Returns a string identifier of the Resource.
   * @return string
   */
  public function getResourceId() {
    return $this->resourceId;
  }

  public function getId() {
    return $this->id;
  }
}