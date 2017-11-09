<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\Entities\MagicAccessors;

/**
 * @ORM\MappedSuperclass
 *
 * @method string getId()
 * @method string getResultsUrl()
 * @method string setResultsUrl(string $url)
 * @method string getJobConfigPath()
 */
abstract class Submission
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
  protected $submittedAt;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $submittedBy;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $resultsUrl;

  /**
   * @ORM\Column(type="string")
   */
  protected $jobConfigPath;

  public function canBeEvaluated(): bool {
    return $this->resultsUrl !== NULL;
  }


  public function __construct(User $submittedBy, string $jobConfigPath) {
    $this->submittedAt = new DateTime;
    $this->submittedBy = $submittedBy;
    $this->jobConfigPath = $jobConfigPath;
  }

}
