<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;
use App\Model\Repository\Comments;

/**
 * Endpoints for comment manipulation
 * @LoggedIn
 */
class CommentsPresenter extends BasePresenter {

  /**
   * @var Comments
   * @inject
   */
  public $comments;

  /**
   *
   * @param string $id
   * @return CommentThread
   */
  protected function findThreadOrCreateIt(string $id) {
    $thread = $this->comments->get($id);
    if (!$thread) {
      $thread = CommentThread::createThread($id);
      $this->comments->persistThread($thread);
    }

    return $thread;
  }

  /**
   * Get a comment thread
   * @GET
   * @UserIsAllowed(comments="view")
   */
  public function actionDefault($id) {
    $thread = $this->findThreadOrCreateIt($id);
    $this->comments->flush();
    $user = $this->users->findCurrentUserOrThrow();
    $thread->filterPublic($user);
    $this->sendSuccessResponse($thread);
  }

  /**
   * Add a comment to a thread
   * @POST
   * @UserIsAllowed(comments="alter")
   * @Param(type="post", name="text", validation="string:1..", description="Text of the comment")
   * @Param(type="post", name="isPrivate", validation="string", description="True if the comment is private")
   */
  public function actionAddComment(string $id) {
    $thread = $this->findThreadOrCreateIt($id);
    $user = $this->users->findCurrentUserOrThrow();
    $text = $this->getHttpRequest()->getPost("text");
    $isPrivate = $this->getHttpRequest()->getPost("isPrivate") === "yes";
    $comment = Comment::createComment($thread, $user, $text, $isPrivate);

    $this->comments->persistComment($comment);
    $this->comments->persistThread($thread);
    $this->comments->flush();

    $this->sendSuccessResponse($comment);
  }

  /**
   * Make a private comment public or vice versa
   * @POST
   * @UserIsAllowed(comments="alter")
   */
  public function actionTogglePrivate(string $threadId, string $commentId) {
    $user = $this->users->findCurrentUserOrThrow();
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
