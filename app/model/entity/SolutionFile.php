<?php

namespace App\Model\Entity;

use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\IImmutableFile;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * @method Solution getSolution()
 */
class SolutionFile extends UploadedFile implements JsonSerializable
{
    use MagicAccessors;

    /**
     * @ORM\ManyToOne(targetEntity="Solution")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $solution;

    public function __construct($name, DateTime $uploadedAt, $fileSize, ?User $user, Solution $solution)
    {
        parent::__construct($name, $uploadedAt, $fileSize, $user);
        $this->solution = $solution;
        $solution->addFile($this);
    }

    public static function fromUploadedFile(UploadedFile $file, Solution $solution)
    {
        return new self(
            $file->getName(),
            $file->getUploadedAt(),
            $file->getFileSize(),
            $file->getUser(),
            $solution
        );
    }

    public function getFile(FileStorageManager $manager): ?IImmutableFile
    {
        return $manager->getSolutionFile($this->getSolution(), $this->getName());
    }
}
