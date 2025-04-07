<?php

namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Entity\Pipeline;
use App\Model\Entity\RuntimeEnvironment;
use App\Security\ACL\IPipelinePermissions;

class PipelineViewFactory
{
    /** @var IPipelinePermissions */
    private $pipelineAcl;

    public function __construct(IPipelinePermissions $pipelineAcl)
    {
        $this->pipelineAcl = $pipelineAcl;
    }


    /**
     * @param Pipeline[] $pipelines
     * @return array
     */
    public function getPipelines(array $pipelines): array
    {
        return array_map(
            function (Pipeline $pipeline) {
                return $this->getPipeline($pipeline);
            },
            $pipelines
        );
    }

    public function getPipeline(Pipeline $pipeline)
    {
        return [
            "id" => $pipeline->getId(),
            "name" => $pipeline->getName(),
            "version" => $pipeline->getVersion(),
            "createdAt" => $pipeline->getCreatedAt()->getTimestamp(),
            "updatedAt" => $pipeline->getUpdatedAt()->getTimestamp(),
            "description" => $pipeline->getDescription(),
            "author" => $pipeline->getAuthor() ? $pipeline->getAuthor()->getId() : null,
            "forkedFrom" => $pipeline->getCreatedFrom() ? $pipeline->getCreatedFrom()->getId() : null,
            "supplementaryFilesIds" => $pipeline->getSupplementaryFilesIds(),
            "pipeline" => $pipeline->getPipelineConfig()->getParsedPipeline(),
            "parameters" => array_merge(Pipeline::DEFAULT_PARAMETERS, $pipeline->getParameters()->toArray()),
            "runtimeEnvironmentIds" => $pipeline->getRuntimeEnvironments()->map(
                function (RuntimeEnvironment $env) {
                    return $env->getId();
                }
            )->getValues(),
            "permissionHints" => PermissionHints::get($this->pipelineAcl, $pipeline)
        ];
    }
}
