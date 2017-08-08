<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;


/**
 * Class Tree
 */
class MergeTree {

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
   * @return MergeTree
   */
  public function setOtherNodes(array $nodes): MergeTree {
    $this->otherNodes = $nodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return MergeTree
   */
  public function addOtherNode(Node $node): MergeTree {
    $this->otherNodes[] = $node;
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
   * @return MergeTree
   */
  public function setInputNodes(array $inputNodes): MergeTree {
    $this->inputNodes = $inputNodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return MergeTree
   */
  public function addInputNode(Node $node): MergeTree {
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
   * @return MergeTree
   */
  public function setOutputNodes(array $outputNodes): MergeTree {
    $this->outputNodes = $outputNodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return MergeTree
   */
  public function addOutputNode(Node $node): MergeTree {
    $this->outputNodes[] = $node;
    return $this;
  }

}
