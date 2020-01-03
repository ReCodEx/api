<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\VariablesTable;

/**
 * Node representing Box in the compilation of exercise. It can hold additional
 * information regarding box which does not have to be stored there, this can
 * save memory during loading of pipelines and not compiling them.
 * @note Structure used in exercise compilation.
 */
class Node
{

    /**
     * PortNode from which was this node created.
     * @var PortNode
     */
    private $portNode;

    /**
     * Box connected to this node.
     * @var Box
     */
    private $box;

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
     * Identification of tasks which was compiled from corresponding box.
     * @var string[]
     */
    private $taskIds = [];

    /**
     * Nodes which identify themselves as parent of this node.
     * @var Node[]
     */
    private $parents = array();

    /**
     * Children nodes of this one.
     * @var Node[]
     */
    private $children = array();

    /**
     * Dependencies of this node.
     * @var Node[]
     */
    private $dependencies = array();


    /**
     * Node constructor.
     * @param PortNode $node
     */
    public function __construct(PortNode $node = null)
    {
        if ($node) {
            $this->portNode = $node;
            $node->setNode($this);

            $this->box = $node->getBox();
            $this->pipelineId = $node->getPipelineId();
            $this->testId = $node->getTestId();
        }
    }


    /**
     * Return PortNode from which this node was created.
     * @note PortNode structure will not be updated during compilation, therefore it might contain old relations.
     * @return PortNode|null
     */
    public function getPortNode(): ?PortNode
    {
        return $this->portNode;
    }

    /**
     * Get box associated with this node.
     * @return Box
     */
    public function getBox(): Box
    {
        return $this->box;
    }

    /**
     * Set box associated with this node.
     * @param Box $box
     * @return Node
     */
    public function setBox(Box $box): Node
    {
        $this->box = $box;
        return $this;
    }

    /**
     * Test identification for corresponding box.
     * @return string|null
     */
    public function getTestId(): ?string
    {
        return $this->testId;
    }

    /**
     * Set test identification of box.
     * @param string|null $testId
     * @return Node
     */
    public function setTestId(?string $testId): Node
    {
        $this->testId = $testId;
        return $this;
    }

    /**
     * Pipeline identification for corresponding box.
     * @return string|null
     */
    public function getPipelineId(): ?string
    {
        return $this->pipelineId;
    }

    /**
     * Set pipeline identification of box.
     * @param string|null $pipelineId
     * @return Node
     */
    public function setPipelineId(?string $pipelineId): Node
    {
        $this->pipelineId = $pipelineId;
        return $this;
    }

    /**
     * Return task identifications associated with this node.
     * If there is none, ask dependencies for their task identifications.
     * @return string[]
     */
    public function getTaskIds(): array
    {
        $taskIds = $this->taskIds;
        if (empty($taskIds)) {
            foreach ($this->dependencies as $dependency) {
                $taskIds = array_merge($taskIds, $dependency->getTaskIds());
            }
        }

        return array_unique($taskIds);
    }

    /**
     * Add task identification to internal array.
     * @param string $taskId
     */
    public function addTaskId(string $taskId)
    {
        $this->taskIds[] = $taskId;
    }


    /**
     * Get parents of this node.
     * @return Node[]
     */
    public function getParents(): array
    {
        return $this->parents;
    }

    /**
     * Clear parents of node.
     */
    public function clearParents()
    {
        $this->parents = [];
    }

    /**
     * Add parent of this node.
     * @param Node $parent
     */
    public function addParent(Node $parent)
    {
        if (array_search($parent, $this->parents, true) === false) {
            $this->parents[] = $parent;
        }
    }

    /**
     * Remove given parent from this node.
     * @param Node $parent
     */
    public function removeParent(Node $parent)
    {
        if (($key = array_search($parent, $this->parents, true)) !== false) {
            unset($this->parents[$key]);
            $this->parents = array_values($this->parents);
        }
    }

    /**
     * Get children of this node.
     * @return Node[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Add child to this node with specified node.
     * @param Node $child
     */
    public function addChild(Node $child)
    {
        if (array_search($child, $this->children, true) === false) {
            $this->children[] = $child;
        }
    }

    /**
     * Remove given child from children array.
     * @param Node $child
     */
    public function removeChild(Node $child)
    {
        if (($key = array_search($child, $this->children, true)) !== false) {
            unset($this->children[$key]);
            $this->children = array_values($this->children);
        }
    }

    /**
     * Get dependencies of this node.
     * @return Node[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Add dependency of this node.
     * @param Node $dependency
     */
    public function addDependency(Node $dependency)
    {
        if (array_search($dependency, $this->dependencies, true) === false) {
            $this->dependencies[] = $dependency;
        }
    }

    /**
     * Remove given dependency from dependencies array.
     * @param Node $dependency
     */
    public function removeDependency(Node $dependency)
    {
        if (($key = array_search($dependency, $this->dependencies, true)) !== false) {
            unset($this->dependencies[$key]);
            $this->dependencies = array_values($this->dependencies);
        }
    }
}
