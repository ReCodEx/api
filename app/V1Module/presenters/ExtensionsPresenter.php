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

    public function checkUrl(string $extId, string $instanceId)
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
     */
    public function actionUrl(string $extId, string $instanceId, ?string $locale)
    {
        $user = $this->getCurrentUser();
        $extension = $this->extensions->getExtension($extId);

        $token = $this->accessManager->issueToken(
            $user,
            null,
            [TokenScope::EXTENSIONS],
            $extension->getUrlTokenExpiration(),
            ["instance" => $instanceId, "extension" => $extId]
        );

        if (!$locale) {
            $locale = $this->getCurrentUserLocale();
        }

        $this->sendSuccessResponse($extension->getUrl($token, $locale));
    }

    public function checkToken(string $extId)
    {
        /*
         * This checker does not employ traditional ACLs for permission checks since it is trvial and it is better
         * to keep everything here (in one place). However, this may change in the future should the presenter get
         * more complex.
         * This action expects to be authenticated by temporary token generated in 'url' action.
         */

        // All users within this scope are allowed the operation...
        $this->isInScope(TokenScope::EXTENSIONS);

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
        $user = $this->getCurrentUser();
        $extension = $this->extensions->getExtension($extId);
        $authUser = $extension->getTokenUserId() ? $this->users->findOrThrow($extension->getTokenUserId()) : $user;

        $token = $this->accessManager->issueToken(
            $authUser,
            null,
            $extension->getTokenScopes(),
            $extension->getTokenExpiration(),
        );

        $this->sendSuccessResponse([
            "accessToken" => $token,
            "user" => $this->userViewFactory->getFullUser($user, false /* do not show really everything */),
        ]);
    }
}
