<?php
namespace App\Model\Entity;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;
use Doctrine\ORM\Mapping as ORM;
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
   */
  protected $solution;

  public function __construct($name, DateTime $uploadedAt, $fileSize, User $user, $filePath, Solution $solution)
  {
    parent::__construct($name, $uploadedAt, $fileSize, $user, $filePath);
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
      $file->getLocalFilePath(),
      $solution
    );
  }
}