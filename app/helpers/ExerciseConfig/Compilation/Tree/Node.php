<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;


/**
 * Class Node
 * @note Box does not have to be assigned. If there is none node is only meant
 * to be some kind of bridge between two pipelines.
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
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $visited = false;

  /**
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $finished = false;


  /**
   * Node constructor.
   * @param Box $box
   */
  public function __construct(Box $box = null) {
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
   * @param array $parents
   */
  public function setParents(array $parents) {
    $this->parents = $parents;
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
   * @param array $children
   */
  public function setChildren(array $children) {
    $this->children = $children;
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
   */
  public function setInTree(bool $flag) {
    $this->isInTree = $flag;
  }

  /**
   * Was this box visited in topological sort.
   * @return bool
   */
  public function isVisited(): bool {
    return $this->visited;
  }

  /**
   * Set visited flag.
   * @param bool $flag
   */
  public function setVisited(bool $flag) {
    $this->visited = $flag;
  }

  /**
   * Was this box finished in topological sort.
   * @return bool
   */
  public function isFinished(): bool {
    return $this->finished;
  }

  /**
   * Set finished flag.
   * @param bool $flag
   */
  public function setFinished(bool $flag) {
    $this->finished = $flag;
  }

}
