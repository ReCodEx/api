<?php

namespace App\Helpers;

use App\Model\Entity\LocalizedEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Nette\StaticClass;

class Localizations
{
    use StaticClass;

    public const PRIMARY_LOCALE = "cs";

    /**
     * Update a collection of localized entities with new versions if necessary
     * (i.e. only replace original entities with new ones if they are not considered equal).
     * For the new entities, the parent association (createdFrom) is set automatically.
     * If an entity in the collection has no counterpart among the updated entities, it is removed.
     * @param Collection $collection the collection to be updated
     * @param array $updatedLocalizations the updated entities
     */
    public static function updateCollection(Collection $collection, $updatedLocalizations)
    {
        $updatedLocalizations = new ArrayCollection($updatedLocalizations);

        /** @var LocalizedEntity $localization */
        foreach ($updatedLocalizations as $localization) {
            $original = $collection->filter(
                function (LocalizedEntity $candidate) use ($localization) {
                    return $localization->getLocale() === $candidate->getLocale();
                }
            )->first();

            if (!$original || !$localization->equals($original)) {
                $collection->add($localization);

                if ($original) {
                    $collection->removeElement($original);
                    $localization->setCreatedFrom($original);
                }
            }
        }

        $toRemove = [];

        foreach ($collection as $localization) {
            if (
                !$updatedLocalizations->exists(
                    function ($key, LocalizedEntity $entity) use ($localization) {
                        return $entity->getLocale() === $localization->getLocale();
                    }
                )
            ) {
                $toRemove[] = $localization;
            }
        }

        foreach ($toRemove as $entity) {
            $collection->removeElement($entity);
        }
    }

    public static function getPrimaryLocalization(Collection $collection): ?LocalizedEntity
    {
        /** @var LocalizedEntity $text */
        foreach ($collection as $text) {
            if ($text->getLocale() === self::PRIMARY_LOCALE) {
                return $text;
            }
        }

        return !$collection->isEmpty() ? $collection->first() : null;
    }
}
