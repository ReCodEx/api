<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\VariablesTable;
use Nette\Utils\Arrays;


/**
 * Node representing Box in the compilation of exercise. It can hold additional
 * information regarding box which does not have to be stored there, this can
 * save memory during loading of pipelines and not compiling them.
 * This node contains children and parents indexed by corresponding port.
 * @note Structure used in exercise compilation.
 */
class PortNode {

  /**
   * Box connected to this node.
   * @var Box
   */
  private $box;

  /**
   * Nodes which identify themselves as parent of this node, ndexed by port
   * name.
   * @var PortNode[]
   */
  private $parents = array();

  /**
   * Children nodes of this one.
   * @var PortNode[]
   */
  private $children = array();

  /**
   * Children nodes of this one, indexed by port name.
   * @var array
   */
  private $childrenByPort = array();

  /**
   * Is this node contained in created tree.
   * Flag regarding tree construction.
   * @var bool
   */
  private $isInTree = false;

  /**
   * Tree was visited during topological sorting.
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $visited = false;

  /**
   * Tree was finished and does not have to be processed again.
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $finished = false;

  /**
   * Identification of test to which this box belongs to.
   * @var string
   */
  private $testId = null;

  /**
   * Identification of pipeline to which this box belongs to.
   * @var string
   */
  private $pipelineId = null;


  /**
   * Node constructor.
   * @param Box $box
   * @param string $pipelineId
   * @param string|null $testId
   */
  public function __construct(Box $box, string $pipelineId = null, string $testId = null) {
    $this->box = $box;
    $this->pipelineId = $pipelineId;
    $this->testId = $testId;
  }

  /**
   * Get box associated with this node.
   * @return Box
   */
  public function getBox(): Box {
    return $this->box;
  }


  /**
   * Get parents of this node.
   * @return PortNode[]
   */
  public function getParents(): array {
    return $this->parents;
  }

  /**
   * Get parent for specified port.
   * @param string $port
   * @return PortNode|null
   */
  public function getParent(string $port): ?PortNode {
    return Arrays::get($this->parents, $port, null);
  }

  /**
   * Find port of given parent.
   * @param PortNode $node
   * @return null|string
   */
  public function findParentPort(PortNode $node): ?string {
    $portName = array_search($node, $this->parents, true);
    return $portName ? $portName : null;
  }

  /**
   * Clear parents of this node.
   */
  public function clearParents() {
    $this->parents = array();
  }

  /**
   * Add parent of this node.
   * @param string $port
   * @param PortNode $parent
   */
  public function addParent(string $port, PortNode $parent) {
    $this->parents[$port] = $parent;
  }

  /**
   * Remove given parent from this node.
   * @param PortNode $parent
   */
  public function removeParent(PortNode $parent) {
    if(($key = array_search($parent, $this->parents)) !== false){
      unset($this->parents[$key]);
    }
  }

  /**
   * Get children of this node.
   * @return PortNode[]
   */
  public function getChildren(): array {
    return $this->children;
  }

  /**
   * Get children of this node indexed by port.
   * @return array
   */
  public function getChildrenByPort(): array {
    return $this->childrenByPort;
  }

  /**
   * Get children with given port.
   * @param string $port
   * @return PortNode[]
   */
  public function getChildrenByPortName(string $port): array {
    return Arrays::get($this->childrenByPort, $port, []);
  }

  /**
   * Find port of given child.
   * @param PortNode $node
   * @return null|string
   */
  public function findChildPort(PortNode $node): ?string {
    foreach ($this->childrenByPort as $portName => $children) {
      if (array_search($node, $children, true) !== false) {
        return $portName;
      }
    }
    return null;
  }

  /**
   * Clear children array.
   */
  public function clearChildren() {
    $this->children = array();
    $this->childrenByPort = array();
  }

  /**
   * Add child to this node with specified node.
   * @param string $port
   * @param PortNode $child
   */
  public function addChild(string $port, PortNode $child) {
    $this->children[] = $child;
    if (!array_key_exists($port, $this->childrenByPort)) {
      $this->childrenByPort[$port] = [];
    }
    $this->childrenByPort[$port][] = $child;
  }

  /**
   * Remove given child from children array.
   * @param PortNode $child
   */
  public function removeChild(PortNode $child) {
    if(($key = array_search($child, $this->children)) !== false){
      unset($this->children[$key]);
    }

    foreach ($this->childrenByPort as $port => $children) {
      if(($key = array_search($child, $children)) !== false){
        unset($this->childrenByPort[$port][$key]);
      }
    }
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

  /**
   * Test identification for corresponding box.
   * @return string|null
   */
  public function getTestId(): ?string {
    return $this->testId;
  }

  /**
   * Pipeline identification for corresponding box.
   * @return string|null
   */
  public function getPipelineId(): ?string {
    return $this->pipelineId;
  }

}
