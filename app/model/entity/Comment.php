<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Comment implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="CommentThread", inversedBy="comments")
   */
  protected $commentThread;
  
  /**
   * @ORM\OneToOne(targetEntity="User")
   */
  protected $user;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPrivate;

  /**
   * @return void
   */
  public function togglePrivate() {
    $this->isPrivate = !$this->isPrivate;
  }

  /**
    * @ORM\Column(type="datetime")
    */
  protected $postedAt;
  
  /**
    * @ORM\Column(type="string")
    */
  protected $text;

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "commentThreadId" => $this->commentThread->getId(),
      "user" => [
        "id" => $this->user->getId(),
        "name" => $this->user->getName(),
        "avatarUrl" => $this->user->getAvatarUrl()
      ],
      "postedAt" => $this->postedAt->getTimestamp(),
      "isPrivate" => $this->isPrivate,
      "text" => $this->text
    ];
  }

  public static function createComment(CommentThread $thread, User $user, $text, $isPrivate = FALSE) {
    $comment = new Comment;
    $comment->commentThread = $thread;
    $comment->user = $user;
    $comment->postedAt = new \DateTime;
    $comment->text = $text;
    $comment->isPrivate = $isPrivate;
    $thread->addComment($comment);
    return $comment;
  }

}
