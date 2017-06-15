<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn("discriminator")
 * @ORM\Table(name="`uploaded_file`")
 *
 * @method string getName()
 * @method string getLocalFilePath()
 * @method DateTime getUploadedAt()
 * @method int getFileSize()
 * @method User getUser()
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
     * The name under which the file was uploaded
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * A complete path to the file on local filesystem. If NULL, the file is not present.
     * @ORM\Column(type="string", nullable=true)
     */
    protected $localFilePath;

    public function getContent() {
      return $this->localFilePath !== NULL ? file_get_contents($this->localFilePath) : NULL;
    }

    /**
    * Extract extension from this file and return it.
    * @return string extension
    */
    public function getFileExtension(): string {
      return pathinfo($this->name, PATHINFO_EXTENSION) ?? "";
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $uploadedAt;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isPublic;

    /**
     * @ORM\Column(type="integer")
     */
    protected $fileSize;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;

    public function getId() {
      return $this->id;
    }

  /**
   * @param string $name Name of the file
   * @param DateTime $uploadedAt Time of the upload
   * @param int $fileSize Size of the file
   * @param User $user The user who uploaded the file
   * @param string $filePath Path where the file is stored
   * @param bool $isPublic
   */
    public function __construct(string $name, DateTime $uploadedAt, int $fileSize, User $user, string $filePath = null, $isPublic = FALSE) {
      $this->localFilePath = $filePath;
      $this->name = $name;
      $this->uploadedAt = $uploadedAt;
      $this->fileSize = $fileSize;
      $this->user = $user;
      $this->isPublic = $isPublic;
    }

    public function jsonSerialize() {
      return [
        "id" => $this->id,
        "name" => $this->name,
        "size" => $this->fileSize,
        "uploadedAt" => $this->uploadedAt->getTimestamp(),
        "userId" => $this->user->getId(),
        "isPublic" => $this->isPublic
      ];
    }

    public function isPublic() {
      return $this->isPublic;
    }
}
