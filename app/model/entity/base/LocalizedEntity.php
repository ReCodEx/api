<?php
namespace App\Model\Entity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;

/**
 * @ORM\MappedSuperclass
 * @method string getId()
 * @method string getLocale()
 * @method DateTime getCreatedAt()
 */
abstract class LocalizedEntity
{
  use MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="datetime")
  */
  protected $createdAt;

  /**
   * @ORM\Column(type="string")
   */
  protected $locale;

  public function __construct(string $locale) {
    $this->locale = $locale;
    $this->createdAt = new DateTime();
  }

  public abstract function equals(LocalizedEntity $entity): bool;

  public abstract function setCreatedFrom(LocalizedEntity $entity);
}
