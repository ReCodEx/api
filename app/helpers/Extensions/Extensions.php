<?php

namespace App\Helpers;

use App\Model\Entity\Instance;
use App\Model\Entity\User;
use Nette;

/**
 * Configuration and related management of ReCodEx extensions. An extension is a 3rd party webapp that can be used
 * to cooperate with ReCodEx (e.g., for user-membership management based on external university system).
 * An extension has a URL which is injected with tmp token (holding the ID of currently logged user).
 * The tmp token can be used by the extension to fetch a full token which can be used to access the API on behalf
 * of the logged in user.
 */
class Extensions
{
    use Nette\SmartObject;

    protected array $extensions = [];

    public function __construct(array $extensions)
    {
        foreach ($extensions as $config) {
            $extension = new ExtensionConfig($config);
            $this->extensions[$extension->getId()] = $extension;
        }
    }

    /**
     * Retrieve the extension by its ID.
     * @param string $id
     * @return ExtensionConfig|null null is returned if no such extension exists
     */
    public function getExtension(string $id): ?ExtensionConfig
    {
        return $this->extensions[$id] ?? null;
    }

    /**
     * Filter out extensions that are accessible by given user in given instance.
     * @param Instance $instance
     * @param User $user
     * @return ExtensionConfig[] array indexed by extension IDs
     */
    public function getAccessibleExtensions(Instance $instance, User $user): array
    {
        $res = [];
        foreach ($this->extensions as $id => $extension) {
            if ($extension->isAccessible($instance, $user)) {
                $res[$id] = $extension;
            }
        }
        return $res;
    }
}
