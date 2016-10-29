<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

use Nette\Utils\Json;
use DateTime;

/**
 * @ORM\Entity
 */
class UserAction implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $user;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $loggedAt;

  /**
   * @ORM\Column(type="string")
   */
  protected $action;

  /**
   * @ORM\Column(type="string")
   */
  protected $params;

  /**
   * @ORM\Column(type="integer")
   */
  protected $code;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $data;

  /**
   * @param User     $user
   * @param DateTime $loggedAt    Time of the action
   * @param string   $action      Action name
   * @param array    $params      Parameters of the action
   * @param int      $code        HTTP response code
   * @param mixed    $data        Additional data
   */
  public function __construct(User $user, DateTime $loggedAt, string $action, array $params, int $code, $data = NULL) {
    $this->user = $user;
    $this->loggedAt = $loggedAt;
    $this->action = $action;
    $this->params = Json::encode($params);
    $this->code = $code;
    $this->data = Json::encode($data);
  }

  public function jsonSerialize() {
    return [
      "loggedAt"  => $this->loggedAt,
      "userId"    => $this->user->getId(),
      "action"    => $this->action,
      "code"      => $this->code
    ];
  }

}
