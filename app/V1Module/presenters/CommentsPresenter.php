<?php

namespace App\V1Module\Presenters;

use App\Exception\NotFoundException;

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
    $this->sendSuccessResponse($thread);
  }

  /**
   * @POST
   * @RequiredField(type="post", name="text", validation="string:1..")
   */
  public function actionAddComment(string $id) {
    $thread = $this->findThreadOrCreateIt($id);
    $user = $this->findUserOrThrow('me');
    $text = $this->getHttpRequest()->getPost('text');
    $comment = Comment::createComment($thread, $user, $text);
    
    $this->comments->persistComment($comment);
    $this->comments->persistThread($thread);
    $this->comments->flush();

    $this->sendSuccessResponse($comment);
  }

}
