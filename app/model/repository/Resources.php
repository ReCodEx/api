<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Resource;

class Resources extends Nette\Object {

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

    public function persist(Resource $resource) {
        $this->em->persist($resource);
    }
}
