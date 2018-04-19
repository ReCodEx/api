<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 *
 * @method bool getDarkTheme()
 * @method bool getVimMode()
 * @method bool getOpenedSidebar()
 * @method bool getUseGravatar()
 * @method string getDefaultLanguage()
 * @method bool getNewAssignmentEmails()
 * @method bool getAssignmentDeadlineEmails()
 * @method bool getSubmissionEvaluatedEmails()
 * @method setDarkTheme(bool $darkTheme)
 * @method setVimMode(bool $vimMode)
 * @method setOpenedSidebar(bool $opened)
 * @method setUseGravatar(bool $use)
 * @method setDefaultLanguage(bool $language)
 * @method setNewAssignmentEmails(bool $opened)
 * @method setAssignmentDeadlineEmails(bool $opened)
 * @method setSubmissionEvaluatedEmails(bool $opened)
 */
class UserSettings implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

  public function __construct(
    bool $darkTheme = true,
    bool $vimMode = false,
    string $defaultLanguage = "en",
    bool $openedSidebar = true,
    bool $useGravatar = true
  ) {
    $this->darkTheme = $darkTheme;
    $this->vimMode = $vimMode;
    $this->defaultLanguage = $defaultLanguage;
    $this->openedSidebar = $openedSidebar;
    $this->useGravatar = $useGravatar;

    $this->newAssignmentEmails = true;
    $this->assignmentDeadlineEmails = true;
    $this->submissionEvaluatedEmails = true;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $darkTheme;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $vimMode;

  /**
   * @ORM\Column(type="string")
   */
  protected $defaultLanguage;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $openedSidebar;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $useGravatar;


  /*******************
   * Emails settings *
   *******************/

  /**
   * @ORM\Column(type="boolean")
   */
  protected $newAssignmentEmails;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $assignmentDeadlineEmails;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $submissionEvaluatedEmails;


  public function jsonSerialize() {
    return [
      "darkTheme" => $this->darkTheme,
      "vimMode" => $this->vimMode,
      "defaultLanguage" => $this->defaultLanguage,
      "openedSidebar" => $this->openedSidebar,
      "useGravatar" => $this->useGravatar,
      "newAssignmentEmails" => $this->newAssignmentEmails,
      "assignmentDeadlineEmails" => $this->assignmentDeadlineEmails,
      "submissionEvaluatedEmails" => $this->submissionEvaluatedEmails
    ];
  }
}
