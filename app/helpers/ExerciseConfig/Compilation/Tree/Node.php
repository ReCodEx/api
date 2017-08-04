<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;


/**
 * Class Node
 */
class Node {

  /**
   * @var Node[]
   */
  private $parents = array();

  /**
   * @var Node[]
   */
  private $children = array();

  /**
   * @var bool
   */
  private $visited = false;


  /**
   * @return Node[]
   */
  public function getParents(): array {
    return $this->parents;
  }

  /**
   * @param Node $parent
   */
  public function addParent(Node $parent) {
    $this->parents[] = $parent;
  }

  /**
   * @return array
   */
  public function getChildren(): array {
    return $this->children;
  }

  /**
   * @param Node $child
   */
  public function addChild(Node $child) {
    $this->children[] = $child;
  }

}
