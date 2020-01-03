<?php

namespace App\Model\Entity;

use App\Exceptions\ExerciseConfigException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Helpers\YamlException;
use App\Helpers\Yaml;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method RuntimeEnvironment getRuntimeEnvironment()
 */
class ExerciseEnvironmentConfig
{
    use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="RuntimeEnvironment")
     */
    protected $runtimeEnvironment;

    /**
     * @ORM\Column(type="text")
     */
    protected $variablesTable;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * Created from.
     * @ORM\ManyToOne(targetEntity="ExerciseEnvironmentConfig")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @var ExerciseEnvironmentConfig
     */
    protected $createdFrom;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $author;

    public function getAuthor(): ?User
    {
        return $this->author->isDeleted() ? null : $this->author;
    }


    /**
     * RuntimeConfig constructor.
     * @param RuntimeEnvironment $runtimeEnvironment
     * @param string $variablesTable
     * @param User $author
     * @param ExerciseEnvironmentConfig|null $createdFrom
     */
    public function __construct(
        RuntimeEnvironment $runtimeEnvironment,
        string $variablesTable,
        User $author,
        ExerciseEnvironmentConfig $createdFrom = null
    ) {
        $this->runtimeEnvironment = $runtimeEnvironment;
        $this->variablesTable = $variablesTable;
        $this->createdFrom = $createdFrom;
        $this->createdAt = new \DateTime();
        $this->author = $author;
    }

    /**
     * Return array-like structure containing variables table.
     * @return array
     * @throws ExerciseConfigException
     */
    public function getParsedVariablesTable(): array
    {
        try {
            return Yaml::parse($this->variablesTable);
        } catch (YamlException $e) {
            throw new ExerciseConfigException("Variables table is not a valid YAML and it cannot be parsed.");
        }
    }

    public function equals(?ExerciseEnvironmentConfig $other): bool
    {
        if ($other === null) {
            return false;
        }

        try {
            return Yaml::dump($this->getParsedVariablesTable()) === Yaml::dump($other->getParsedVariablesTable());
        } catch (ExerciseConfigException $e) {
            return false;
        }
    }
}
