<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;


/**
 * Class Tree
 */
class Tree {

  /**
   * All nodes contained in tree.
   * @var array
   */
  private $nodes = array();

  /**
   * Nodes containing boxes without input ports.
   * @var Node[]
   */
  private $rootNodes = array();

  /**
   * Nodes with boxes which does not have output ports.
   * @var Node[]
   */
  private $endNodes = array();


  /**
   * @return Node[]
   */
  public function getNodes(): array {
    return $this->nodes;
  }

  /**
   * @param array $nodes
   * @return Tree
   */
  public function setNodes(array $nodes): Tree {
    $this->nodes = $nodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return Tree
   */
  public function addNode(Node $node): Tree {
    $this->nodes[] = $node;
    return $this;
  }

  /**
   * @return Node[]
   */
  public function getRootNodes(): array {
    return $this->rootNodes;
  }

  /**
   * @param array $rootNodes
   * @return Tree
   */
  public function setRootNodes(array $rootNodes): Tree {
    $this->rootNodes = $rootNodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return Tree
   */
  public function addRootNode(Node $node): Tree {
    $this->rootNodes[] = $node;
    return $this;
  }

  /**
   * @return Node[]
   */
  public function getEndNodes(): array {
    return $this->endNodes;
  }

  /**
   * @param array $endNodes
   * @return Tree
   */
  public function setEndNodes(array $endNodes): Tree {
    $this->endNodes = $endNodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return Tree
   */
  public function addEndNode(Node $node): Tree {
    $this->endNodes[] = $node;
    return $this;
  }

}
