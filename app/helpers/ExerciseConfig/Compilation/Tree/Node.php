<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;


/**
 * Class Node
 */
class Node {

  /**
   * @var Box
   */
  private $box;

  /**
   * @var Node[]
   */
  private $parents = array();

  /**
   * @var Node[]
   */
  private $children = array();

  /**
   * Flag regarding tree construction.
   * @var bool
   */
  private $isInTree = false;

  /**
   * @var bool
   */
  private $visited = false;


  /**
   * Node constructor.
   * @param Box $box
   */
  public function __construct(Box $box) {
    $this->box = $box;
  }

  /**
   * Get box associated with this node.
   * @return Box
   */
  public function getBox(): Box {
    return $this->box;
  }

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
   * @param Node $parent
   */
  public function removeParent(Node $parent) {
    $this->parents = array_diff($this->parents, $parent);
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

  /**
   * @param Node $child
   */
  public function removeChild(Node $child) {
    $this->children = array_diff($this->children, $child);
  }

  /**
   * Is this box in tree.
   * @return bool
   */
  public function isInTree(): bool {
    return $this->isInTree;
  }

  /**
   * Set is in tree flag.
   * @param bool $flag
   * @return Box
   */
  public function setInTree(bool $flag): Node {
    $this->isInTree = $flag;
    return $this;
  }

}
