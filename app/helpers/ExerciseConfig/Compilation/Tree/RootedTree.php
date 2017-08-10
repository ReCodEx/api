<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;


/**
 * Structure which is logically tree which contains reference to its root nodes.
 * There can be multiple root nodes which does not have any parents.
 * @note Structure used in exercise compilation.
 */
class RootedTree {

  /**
   * Root nodes in tree.
   * @var Node[]
   */
  private $rootNodes = array();


  /**
   * Get root nodes of tree.
   * @return Node[]
   */
  public function getRootNodes(): array {
    return $this->rootNodes;
  }

  /**
   * Add root node to the tree.
   * @param Node $node
   * @return RootedTree
   */
  public function addRootNode(Node $node): RootedTree {
    $this->rootNodes[] = $node;
    return $this;
  }

}
