<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\Yaml;
use JsonSerializable;

/**
 * High-level configuration environment holder.
 */
class Environment implements JsonSerializable
{

    /** Name of the pipelines key */
    const PIPELINES_KEY = "pipelines";


    /**
     * Array indexed by pipelines name.
     * @var PipelineVars[]
     */
    protected $pipelines = array();


    /**
     * Get pipelines for this environment.
     * @return PipelineVars[]
     */
    public function getPipelines(): array
    {
        return $this->pipelines;
    }

    /**
     * Get pipeline of the given ID.
     * @note There can be multiple pipelines with the same identification.
     * This method will find only the first one.
     * @param string $id
     * @return PipelineVars|null
     */
    public function getPipeline(string $id): ?PipelineVars
    {
        foreach ($this->pipelines as $pipeline) {
            if ($pipeline->getId() === $id) {
                return $pipeline;
            }
        }
        return null;
    }

    /**
     * Add pipeline to this environment.
     * @param PipelineVars $pipeline
     * @return $this
     */
    public function addPipeline(PipelineVars $pipeline): Environment
    {
        $this->pipelines[] = $pipeline;
        return $this;
    }

    /**
     * Remove pipeline with given identification.
     * @param string $id
     * @return $this
     */
    public function removePipeline(string $id): Environment
    {
        $this->pipelines = array_filter(
            $this->pipelines,
            function (PipelineVars $pipeline) use ($id) {
                return $pipeline->getId() !== $id;
            }
        );
        return $this;
    }


    /**
     * Creates and returns properly structured array representing this object.
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        $data[self::PIPELINES_KEY] = array();
        foreach ($this->pipelines as $pipeline) {
            $data[self::PIPELINES_KEY][] = $pipeline->toArray();
        }

        return $data;
    }

    /**
     * Serialize the config.
     * @return string
     */
    public function __toString(): string
    {
        return Yaml::dump($this->toArray());
    }

    /**
     * Enable automatic serialization to JSON
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
