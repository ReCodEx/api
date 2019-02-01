<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;

/**
 * @ORM\Entity
 * @method string getId()
 */
class CommentThread implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="string", length=36, options={"fixed":true})
   */
  protected $id;

  /**
   * @ORM\OneToMany(targetEntity="Comment", mappedBy="commentThread")
   * @ORM\OrderBy({ "postedAt" = "ASC" })
   */
  protected $comments;

  public function addComment(Comment $comment) {
    $this->comments->add($comment);
  }

  /**
   * @return Comment[]
   */
  public function findAllPublic(): array {
    $publicComments = Criteria::create()
      ->where(Criteria::expr()->eq("isPrivate", false));
    return $this->comments->matching($publicComments)->getValues();
  }

  public function filterPublic(User $currentUser) {
    $publicComments = Criteria::create()
      ->where(Criteria::expr()->eq("isPrivate", false))
      ->orWhere(Criteria::expr()->eq("user", $currentUser));
    $this->comments = $this->comments->matching($publicComments);
  }

  public function jsonSerialize() {

    return [
      "id" => $this->id,
      "comments" => $this->comments->toArray()
    ];
  }

  public static function createThread($id) {
    $thread = new CommentThread();
    $thread->id = $id;
    $thread->comments = new ArrayCollection();
    return $thread;
  }

}
