<?php

namespace App\Model\Repository;

use App\Model\Entity\Resource;
use Kdyby\Doctrine\EntityManager;

class Resources {

  private $em;
  private $resources;

  public function __construct(EntityManager $em) {
    $this->em = $em;
    $this->resources = $em->getRepository("App\Model\Entity\Resource");
  }
  
  public function findAll() {
    return $this->resources->findAll();
  }

  public function get($id) {
    return $this->resources->findOneById($id);
  }

  public function persist(Resource $resource, $autoFlush = TRUE) {
    $this->em->persist($resource);
    if ($autoFlush) {
      $this->flush();
    }
  }

  public function flush() {
    $this->em->flush();
  }
}
