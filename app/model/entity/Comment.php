<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Comment implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @var CommentThread
     * @ORM\ManyToOne(targetEntity="CommentThread", inversedBy="comments")
     */
    protected $commentThread;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;

    public function getUser(): ?User
    {
        return $this->user->isDeleted() ? null : $this->user;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isPrivate;

    /**
     * @return void
     */
    public function togglePrivate()
    {
        $this->isPrivate = !$this->isPrivate;
    }

    public function setPrivate(bool $private): void
    {
        $this->isPrivate = $private;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function getThread()
    {
        return $this->commentThread;
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $postedAt;

    /**
     * @ORM\Column(type="text", length=65535)
     */
    protected $text;

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "commentThreadId" => $this->commentThread->getId(),
            "user" => [
                "id" => $this->user->getId(),
                "name" => $this->user->getName(),
                "avatarUrl" => $this->user->getAvatarUrl(),
                "avatarLetter" => !empty($this->user->getFirstName()) ? mb_substr(
                    $this->user->getFirstName(),
                    0,
                    1
                ) : ""
            ],
            "postedAt" => $this->postedAt->getTimestamp(),
            "isPrivate" => $this->isPrivate,
            "text" => $this->text
        ];
    }

    public static function createComment(CommentThread $thread, User $user, string $text, bool $isPrivate = false)
    {
        $comment = new Comment();
        $comment->commentThread = $thread;
        $comment->user = $user;
        $comment->postedAt = new DateTime();
        $comment->text = $text;
        $comment->isPrivate = $isPrivate;
        $thread->addComment($comment);
        return $comment;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getCommentThread(): CommentThread
    {
        return $this->commentThread;
    }

    public function getPostedAt(): DateTime
    {
        return $this->postedAt;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
