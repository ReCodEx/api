<?php

namespace App\Model\Entity;

use App\Helpers\Scheduler\IJob;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn("discriminator")
 * @ORM\Table(name="`scheduler_job`")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method DateTime getNextExecution()
 * @method int|null getDelay()
 * @method setNextExecution(DateTime $nextExecution)
 */
abstract class SchedulerJob
{
  use MagicAccessors;
  use DeleteableEntity;

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
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $lastExecution;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $nextExecution;

  /**
   * Number of seconds, if set the job is repeatable and should be rescheduled after execution.
   * @ORM\Column(type="integer", nullable=true)
   */
  protected $delay;


  /**
   * SchedulerJob constructor.
   * @param DateTime $nextExecution
   * @param int $delay
   * @throws Exception
   */
  public function __construct(DateTime $nextExecution, int $delay) {
    $this->createdAt = new DateTime();
    $this->lastExecution = null;
    $this->nextExecution = $nextExecution;
    $this->delay = $delay;
  }

  public function executedNow() {
    $this->lastExecution = new DateTime();
  }
}
