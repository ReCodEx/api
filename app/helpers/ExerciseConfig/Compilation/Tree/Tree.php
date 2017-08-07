<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;


/**
 * Class Tree
 */
class Tree {

  /**
   * Root nodes in tree.
   * @var Node[]
   */
  private $rootNodes = array();

  /**
   * Nodes which are not input and output ones.
   * @var array
   */
  private $otherNodes = array();

  /**
   * DataInBox nodes.
   * @var Node[]
   */
  private $inputNodes = array();

  /**
   * DataOutBox nodes.
   * @var Node[]
   */
  private $outputNodes = array();


  /**
   * @return Node[]
   */
  public function getOtherNodes(): array {
    return $this->otherNodes;
  }

  /**
   * @param Node[] $nodes
   * @return Tree
   */
  public function setOtherNodes(array $nodes): Tree {
    $this->otherNodes = $nodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return Tree
   */
  public function addOtherNode(Node $node): Tree {
    $this->otherNodes[] = $node;
    return $this;
  }

  /**
   * @return Node[]
   */
  public function getRootNodes(): array {
    return $this->rootNodes;
  }

  /**
   * @param Node[] $rootNodes
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
  public function getInputNodes(): array {
    return $this->inputNodes;
  }

  /**
   * @param Node[] $inputNodes
   * @return Tree
   */
  public function setInputNodes(array $inputNodes): Tree {
    $this->inputNodes = $inputNodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return Tree
   */
  public function addInputNode(Node $node): Tree {
    $this->inputNodes[] = $node;
    return $this;
  }

  /**
   * @return Node[]
   */
  public function getOutputNodes(): array {
    return $this->outputNodes;
  }

  /**
   * @param Node[] $outputNodes
   * @return Tree
   */
  public function setOutputNodes(array $outputNodes): Tree {
    $this->outputNodes = $outputNodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return Tree
   */
  public function addOutputNode(Node $node): Tree {
    $this->outputNodes[] = $node;
    return $this;
  }

}
