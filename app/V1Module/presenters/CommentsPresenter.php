<?php

namespace App\V1Module\Presenters;

use App\Exception\NotFoundException;
use App\Exception\ForbiddenRequestException;

use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;
use App\Model\Repository\Comments;

/**
 * @LoggedIn
 */
class CommentsPresenter extends BasePresenter {

  /** @var Comments */
  private $comments;

  /**
   * @param Comments $comments  Comments repository
   */
  public function __construct(Comments $comments) {
    $this->comments = $comments;
  }

  protected function findThreadOrCreateIt(string $id) {
    $thread = $this->comments->get($id);
    if (!$thread) {
      $thread = CommentThread::createThread($id);
      $this->comments->persistThread($thread);
    }

    return $thread;
  }

  /**
   * @GET
   */
  public function actionDefault($id) {
    $thread = $this->findThreadOrCreateIt($id);
    $this->comments->flush();
    $user = $this->findUserOrThrow("me");
    $thread->filterPublic($user);
    $this->sendSuccessResponse($thread);
  }

  /**
   * @POST
   * @Param(type="post", name="text", validation="string:1..")
   * @Param(type="post", name="isPrivate", validation="string")
   */
  public function actionAddComment(string $id) {
    $thread = $this->findThreadOrCreateIt($id);
    $user = $this->findUserOrThrow("me");
    $text = $this->getHttpRequest()->getPost("text");
    $isPrivate = $this->getHttpRequest()->getPost("isPrivate") === "yes";
    $comment = Comment::createComment($thread, $user, $text, $isPrivate);
    
    $this->comments->persistComment($comment);
    $this->comments->persistThread($thread);
    $this->comments->flush();

    $this->sendSuccessResponse($comment);
  }

  /**
   * @POST
   */
  public function actionTogglePrivate(string $threadId, string $commentId) {
    $user = $this->findUserOrThrow("me");
    $comment = $this->comments->findUsersComment($user, $commentId);
    
    if (!$comment || $comment->getCommentThread()->getId() !== $threadId) {
      throw new ForbiddenRequestException("This comment does not exist or you cannot access it.");
    }

    $comment->togglePrivate();
    $this->comments->persistComment($comment);
    $this->comments->flush();

    $this->sendSuccessResponse($comment);
  }

}
