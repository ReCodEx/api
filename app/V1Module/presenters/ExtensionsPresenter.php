<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\BadRequestException;
use App\Model\Repository\Instances;
use App\Model\Repository\Users;
use App\Model\View\UserViewFactory;
use App\Helpers\Extensions;
use App\Security\AccessManager;
use App\Security\TokenScope;

/**
 * Endpoints handling 3rd party extensions communication.
 */
class ExtensionsPresenter extends BasePresenter
{
    /**
     * @var Extensions
     * @inject
     */
    public $extensions;

    /**
     * @var Instances
     * @inject
     */
    public $instances;

    /**
     * @var Users
     * @inject
     */
    public $users;

    /**
     * @var AccessManager
     * @inject
     */
    public $accessManager;

    /**
     * @var UserViewFactory
     * @inject
     */
    public $userViewFactory;

    public function noncheckUrl(string $extId, string $instanceId)
    {
        $user = $this->getCurrentUser();
        $extension = $this->extensions->getExtension($extId);
        $instance = $this->instances->findOrThrow($instanceId);
        if (!$extension || !$extension->isAccessible($instance, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Return URL refering to the extension with properly injected temporary JWT token.
     * @GET
     * @Param(type="query", name="locale", required=false, validation="string:2")
     * @Param(type="query", name="return", required=false, validation="string")
     */
    public function actionUrl(string $extId, string $instanceId, ?string $locale, ?string $return)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckToken(string $extId)
    {
        /*
         * This nonchecker does not employ traditional ACLs for permission nonchecks since it is trvial and it is better
         * to keep everything here (in one place). However, this may change in the future should the presenter get
         * more complex.
         * This action expects to be authenticated by temporary token generated in 'url' action.
         */

        // All users within this scope are allowed the operation...
        if (!$this->isInScope(TokenScope::EXTENSIONS)) {
            throw new ForbiddenRequestException();
        }

        // ...but the token must be also valid...
        $token = $this->getAccessToken();
        $instanceId = $token->getPayload('instance');
        if ($token->getPayload('extension') !== $extId || !$instanceId) {
            throw new BadRequestException();
        }

        // ...and the extension must be accessible by the user.
        $user = $this->getCurrentUser();
        $extension = $this->extensions->getExtension($extId);
        $instance = $this->instances->findOrThrow($instanceId);
        if (!$extension || !$extension->isAccessible($instance, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * This endpoint is used by a backend of an extension to get a proper access token
     * (from a temp token passed via URL). It also returns details about authenticated user.
     * @POST
     */
    public function actionToken(string $extId)
    {
        $this->sendSuccessResponse("OK");
    }
}
