<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;
use App\Model\Repository\Comments;
use App\Security\ACL\ICommentPermissions;

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
   * @var ICommentPermissions
   * @inject
   */
  public $commentAcl;

  /**
   * @param string $id
   * @return CommentThread
   * @throws ForbiddenRequestException
   */
  protected function findThreadOrCreateIt(string $id) {
    if (!$this->commentAcl->canCreateThread()) {
      throw new ForbiddenRequestException();
    }

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
   * @param string $id Identifier of the comment thread
   * @throws ForbiddenRequestException
   */
  public function actionDefault($id) {
    $thread = $this->findThreadOrCreateIt($id);
    if (!$this->commentAcl->canViewThread($thread)) {
      throw new ForbiddenRequestException();
    }
    $this->comments->flush();
    $user = $this->getCurrentUser();
    $thread->filterPublic($user);
    $this->sendSuccessResponse($thread);
  }

  /**
   * Add a comment to a thread
   * @POST
   * @Param(type="post", name="text", validation="string:1..", description="Text of the comment")
   * @Param(type="post", name="isPrivate", validation="string", description="True if the comment is private")
   * @param string $id Identifier of the comment thread
   * @throws ForbiddenRequestException
   */
  public function actionAddComment(string $id) {
    $thread = $this->findThreadOrCreateIt($id);
    if (!$this->commentAcl->canAddComment($thread)) {
      throw new ForbiddenRequestException();
    }

    $user = $this->getCurrentUser();
    $text = $this->getRequest()->getPost("text");
    $isPrivate = filter_var($this->getRequest()->getPost("isPrivate"), FILTER_VALIDATE_BOOLEAN);
    $comment = Comment::createComment($thread, $user, $text, $isPrivate);

    $this->comments->persistComment($comment);
    $this->comments->persistThread($thread);
    $this->comments->flush();

    $this->sendSuccessResponse($comment);
  }

  /**
   * Make a private comment public or vice versa
   * @POST
   * @param string $threadId Identifier of the comment thread
   * @param string $commentId Identifier of the comment
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionTogglePrivate(string $threadId, string $commentId) {
    /** @var Comment $comment */
    $comment = $this->comments->findOrThrow($commentId);

    if ($comment->getThread()->getId() !== $threadId) {
      throw new NotFoundException();
    }

    if (!$this->commentAcl->canAlter($comment)) {
      throw new ForbiddenRequestException();
    }

    $comment->togglePrivate();
    $this->comments->persistComment($comment);
    $this->comments->flush();

    $this->sendSuccessResponse($comment);
  }
}
