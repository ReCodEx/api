<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;


/**
 * Tree like structure used in compilation of exercise configuration for
 * constructing pipeline trees and especially merging them. Therefore this tree
 * contains mainly references to input and output nodes.
 * @note Structure used in exercise compilation.
 */
class MergeTree {

  /**
   * Nodes which are not input and output ones.
   * @var array
   */
  private $otherNodes = array();

  /**
   * DataInBox nodes.
   * @var PortNode[]
   */
  private $inputNodes = array();

  /**
   * DataOutBox nodes.
   * @var PortNode[]
   */
  private $outputNodes = array();


  /**
   * Merge all nodes from this tree and return them.
   * @return PortNode[]
   */
  public function getAllNodes(): array {
    return array_merge($this->inputNodes, $this->otherNodes, $this->outputNodes);
  }

  /**
   * Merge all nodes from this tree, reverse them and return them.
   * @return PortNode[]
   */
  public function getAllReversedNodes(): array {
    return array_merge(
      array_reverse($this->inputNodes),
      array_reverse($this->otherNodes),
      array_reverse($this->outputNodes)
    );
  }

  /**
   * Get nodes which are not input or output ones.
   * @return PortNode[]
   */
  public function getOtherNodes(): array {
    return $this->otherNodes;
  }

  /**
   * Set other nodes.
   * @param PortNode[] $nodes
   * @return MergeTree
   */
  public function setOtherNodes(array $nodes): MergeTree {
    $this->otherNodes = $nodes;
    return $this;
  }

  /**
   * Add non-data node to this tree.
   * @param PortNode $node
   * @return MergeTree
   */
  public function addOtherNode(PortNode $node): MergeTree {
    $this->otherNodes[] = $node;
    return $this;
  }

  /**
   * Get input nodes from this tree.
   * @return PortNode[]
   */
  public function getInputNodes(): array {
    return $this->inputNodes;
  }

  /**
   * Set input nodes of this tree.
   * @param PortNode[] $inputNodes
   * @return MergeTree
   */
  public function setInputNodes(array $inputNodes): MergeTree {
    $this->inputNodes = $inputNodes;
    return $this;
  }

  /**
   * Add one input node to this tree.
   * @param PortNode $node
   * @return MergeTree
   */
  public function addInputNode(PortNode $node): MergeTree {
    $this->inputNodes[] = $node;
    return $this;
  }

  /**
   * Get all output nodes contained in tree.
   * @return PortNode[]
   */
  public function getOutputNodes(): array {
    return $this->outputNodes;
  }

  /**
   * Set output nodes to this tree.
   * @param PortNode[] $outputNodes
   * @return MergeTree
   */
  public function setOutputNodes(array $outputNodes): MergeTree {
    $this->outputNodes = $outputNodes;
    return $this;
  }

  /**
   * Add one output node to this tree.
   * @param PortNode $node
   * @return MergeTree
   */
  public function addOutputNode(PortNode $node): MergeTree {
    $this->outputNodes[] = $node;
    return $this;
  }

}
