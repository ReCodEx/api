<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(name="`uploaded_file`")
 */
class UploadedFile implements JsonSerializable
{
    use \Kdyby\Doctrine\Entities\MagicAccessors;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * @ORM\Column(type="string")
     */
    protected $filePath;

    public function getContent() {
      return file_get_contents($this->filePath);
    }

    /**
    * Extract extension from this file and return it.
    * @return string extension
    */
    public function getFileExtension(): string {
      $ext = pathinfo($this->name, PATHINFO_EXTENSION);
      if ($ext === NULL) {
        $ext = "";
      }

      return $ext;
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $uploadedAt;

    /**
     * @ORM\Column(type="integer")
     */
    protected $fileSize;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="Solution")
     */
    protected $solution;

    /** @var string */
    protected $downloadUrl = NULL;

    /**
     * @param string $filePath Path where the file is stored
     * @param string $name Name of the file
     * @param DateTime $uploadedAt Time of the upload
     * @param int $fileSize Size of the file
     * @param User $user   The user who uploaded the file
     */
    public function __construct(string $filePath, string $name, DateTime $uploadedAt, int $fileSize, User $user) {
      $this->filePath = $filePath;
      $this->name = $name;
      $this->uploadedAt = $uploadedAt;
      $this->fileSize = $fileSize;
      $this->user = $user;
    }

    public function jsonSerialize() {
      return [
        "id" => $this->id,
        "name" => $this->name,
        "size" => $this->fileSize,
        "uploadedAt" => $this->uploadedAt->getTimestamp(),
        "userId" => $this->user->getId(),
        "downloadUrl" => $this->downloadUrl
      ];
    }
}
