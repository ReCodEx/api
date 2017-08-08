<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;


/**
 * Class RootedTree
 */
class RootedTree {

  /**
   * Root nodes in tree.
   * @var Node[]
   */
  private $rootNodes = array();


  /**
   * @return Node[]
   */
  public function getRootNodes(): array {
    return $this->rootNodes;
  }

  /**
   * @param Node[] $rootNodes
   * @return RootedTree
   */
  public function setRootNodes(array $rootNodes): RootedTree {
    $this->rootNodes = $rootNodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return RootedTree
   */
  public function addRootNode(Node $node): RootedTree {
    $this->rootNodes[] = $node;
    return $this;
  }

}
