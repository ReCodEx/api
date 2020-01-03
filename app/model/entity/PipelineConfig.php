<?php

namespace App\Model\Entity;

use App\Exceptions\ExerciseConfigException;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use App\Helpers\YamlException;
use App\Helpers\Yaml;
use DateTime;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getPipelineConfig()
 */
class PipelineConfig
{
    use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="text")
     */
    protected $pipelineConfig;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $author;

    public function getAuthor(): ?User
    {
        return $this->author && $this->author->isDeleted() ? null : $this->author;
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="PipelineConfig")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $createdFrom;

    /**
     * @ORM\OneToMany(targetEntity="Pipeline", mappedBy="pipelineConfig")
     */
    protected $pipelines;

    /**
     * Constructor
     * @param string $pipeline
     * @param User|null $author
     * @param PipelineConfig|null $createdFrom
     * @throws Exception
     */
    public function __construct(
        string $pipeline,
        ?User $author,
        PipelineConfig $createdFrom = null
    ) {
        $this->createdAt = new DateTime();
        $this->pipelines = new ArrayCollection();

        $this->pipelineConfig = $pipeline;
        $this->author = $author;
        $this->createdFrom = $createdFrom;
    }

    /**
     * Return array-like structure containing pipeline.
     * @return array|string
     * @throws ExerciseConfigException
     */
    public function getParsedPipeline()
    {
        try {
            return Yaml::parse($this->pipelineConfig);
        } catch (YamlException $e) {
            throw new ExerciseConfigException("Pipeline is not a valid YAML and it cannot be parsed.");
        }
    }
}
