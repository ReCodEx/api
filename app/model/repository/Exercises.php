<?php

namespace App\Model\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseTag;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\User;
use App\Helpers\Pagination;
use App\Model\Helpers\PaginationDbHelper;

/**
 * @extends BaseSoftDeleteRepository<Exercise>
 */
class Exercises extends BaseSoftDeleteRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Exercise::class);
    }

    /**
     * Replace all runtime configurations in exercise with given ones.
     * @param Exercise $exercise
     * @param array $configs configurations which will be placed to exercise
     * @param bool $flush if true then all changes will be flush at the end
     */
    public function replaceEnvironmentConfigs(Exercise $exercise, array $configs, bool $flush = true): void
    {
        $originalConfigs = $exercise->getExerciseEnvironmentConfigs()->toArray();
        foreach ($configs as $config) {
            $exercise->addExerciseEnvironmentConfig($config);
        }
        foreach ($originalConfigs as $config) {
            $exercise->removeExerciseEnvironmentConfig($config);
        }
        if ($flush) {
            $this->flush();
        }
    }

    /**
     * Search exercises names based on given string.
     * @param string|null $search
     * @return Exercise[]
     */
    public function searchByName(?string $search): array
    {
        if ($search === null || empty($search)) {
            return $this->findAll();
        }

        return $this->searchHelper(
            $search,
            function ($search) {
                $qb = $this->createQueryBuilder("e");
                $sub = $this->em->createQueryBuilder()->select("le")->from(LocalizedExercise::class, "le");
                $sub->andWhere($sub->expr()->isMemberOf("le", "e.localizedTexts"))
                    ->andWhere($qb->expr()->like("le.name", $qb->expr()->literal('%' . $search . '%')));
                $qb->andWhere($qb->expr()->exists($sub->getDQL()));
                return $qb->getQuery()->getResult();
            }
        );
    }

    /**
     * Augment given query builder and add filter that covers groups of residence of the exercise.
     * @param QueryBuilder $qb
     * @param mixed $groupsIds Value of the filter
     * @param Groups $groups Doctrine groups repository
     */
    private function getPreparedForPaginationGroupsFilter(QueryBuilder $qb, $groupsIds, Groups $groups): void
    {
        if (!is_array($groupsIds)) {
            $groupsIds = [$groupsIds];
        }

        // Each group has a separate OR clause ...
        $orExpr = $qb->expr()->orX();
        $counter = 0;
        foreach ($groups->groupsIdsAncestralClosure($groupsIds) as $id) {
            $var = "group" . ++$counter;
            $orExpr->add($qb->expr()->isMemberOf(":$var", "e.groups"));
            $qb->setParameter($var, $id);
        }
        $qb->andWhere($orExpr);
    }

    /**
     * Augment given query builder and add filter that handles runtime environments.
     * @param QueryBuilder $qb
     * @param mixed $envs
     */
    private function getPreparedForPaginationEnvsFilter(QueryBuilder $qb, $envs): void
    {
        if (!is_array($envs)) {
            $envs = [$envs];
        }

        $orExpr = $qb->expr()->orX();
        $counter = 0;
        foreach ($envs as $env) {
            $var = "env" . ++$counter;
            $orExpr->add($qb->expr()->isMemberOf(":$var", "e.runtimeEnvironments"));
            $qb->setParameter($var, $env);
        }
        $qb->andWhere($orExpr);
    }

    /**
     * Get a list of exercises filtered and ordered for pagination.
     * The exercises must be paginated manually, since they are tested by ACLs.
     * @param Pagination $pagination Pagination configuration object.
     * @param Groups $groups Doctrine groups repository
     * @return Exercise[]
     */
    public function getPreparedForPagination(Pagination $pagination, Groups $groups)
    {
        // Welcome to Doctrine HELL! Put your sickbags on standby!

        $qb = $this->createQueryBuilder('e'); // takes care of softdelete cases

        // Filter by instance Id (through group membership) ...
        if ($pagination->hasFilter("instanceId")) {
            $instanceId = trim($pagination->getFilter("instanceId"));

            $sub = $groups->createQueryBuilder("g");
            $sub->andWhere($qb->expr()->eq("g.instance", $qb->expr()->literal($instanceId)))
                ->andWhere($sub->expr()->isMemberOf("g", "e.groups"));

            $qb->andWhere($qb->expr()->exists($sub->getDQL()));
        }

        // Only exercises of given authors ...
        if ($pagination->hasFilter("authorsIds")) {
            $authorIds = $pagination->getFilter("authorsIds");
            if (!is_array($authorIds)) {
                $authorIds = [$authorIds];
            }
            $qb->andWhere($qb->expr()->in("e.author", $authorIds));
        }

        // Only exercises in explicitly given groups (or their ascendants) ...
        if ($pagination->hasFilter("groupsIds")) {
            $this->getPreparedForPaginationGroupsFilter($qb, $pagination->getFilter("groupsIds"), $groups);
        }

        // Only exercises with given tags
        if ($pagination->hasFilter("tags")) {
            $tagNames = $pagination->getFilter("tags");
            if (!is_array($tagNames)) {
                $tagNames = [$tagNames];
            }

            $sub = $qb->getEntityManager()->createQueryBuilder()->select("tags")->from(ExerciseTag::class, "tags");
            $sub->andWhere($qb->expr()->eq("tags.exercise", "e.id")); // only tags of examined exercise
            $sub->andWhere($sub->expr()->in("tags.name", $tagNames)); // at least one tag has to match
            $qb->andWhere($qb->expr()->exists($sub->getDQL()));
        }

        // Only exercises of specific RTEs (at least one RTE is present)
        if ($pagination->hasFilter("runtimeEnvironments", true)) { // true = not empty
            $this->getPreparedForPaginationEnvsFilter($qb, $pagination->getFilter("runtimeEnvironments"));
        }

        if ($pagination->getOrderBy() === "name") {
            // Special patch, we need to load localized names from another entity ...
            // Note: This requires custom COALESCE_SUB, which is actually normal COALESCE function that allows subqueries inside in DQL
            $qb->addSelect(
                'COALESCE_SUB(
          (SELECT le1.name FROM App\Model\Entity\LocalizedExercise AS le1 WHERE le1 MEMBER OF e.localizedTexts AND le1.locale = :locale),
          (SELECT le2.name FROM App\Model\Entity\LocalizedExercise AS le2 WHERE le2 MEMBER OF e.localizedTexts AND le2.locale = \'en\'),
          (SELECT MAX(le3.name) FROM App\Model\Entity\LocalizedExercise AS le3 WHERE le3 MEMBER OF e.localizedTexts)
        ) AS HIDDEN localizedName'
            );
            $qb->setParameter('locale', $pagination->getLocale() ?? 'en');
        }

        // Apply common pagination stuff (search and ordering) and yield the results ...
        $paginationDbHelper = new PaginationDbHelper(
            [ // known order by columns
                'name' => ['localizedName'], // HIDDEN column created by special patch
                'createdAt' => ['e.createdAt'],
            ],
            ['name'], // search column names
            LocalizedExercise::class // search is performed on external localized texts
        );
        $paginationDbHelper->apply($qb, $pagination);
        return $paginationDbHelper->getResult($qb, $pagination);
    }

    /**
     * Get distinct authors of all exercises.
     * @param string|null $instanceId ID of an instance from which the authors are selected.
     * @param string|null $groupId A group which restricts the exercies.
     *                             Only exercises attached to that group (or any ancestral group) are considered.
     * @param Groups $groups groups repository
     * @return User[] List of exercises authors.
     */
    public function getAuthors(?string $instanceId, ?string $groupId, Groups $groups)
    {
        $qb = $this->em->createQueryBuilder()->select("a")->from(User::class, "a");
        if ($instanceId) {
            $qb->andWhere(":instance MEMBER OF a.instances")->setParameter("instance", $instanceId);
        }

        $sub = $this->createQueryBuilder("e"); // takes care of softdelete cases
        $sub->andWhere("a = e.author");

        if ($groupId) {
            // Each group of the ancestral closure has a separate OR clause ...
            $orExpr = $sub->expr()->orX();
            $gcounter = 0;
            foreach ($groups->groupsIdsAncestralClosure([$groupId]) as $id) {
                $var = "group" . ++$gcounter;
                $orExpr->add($sub->expr()->isMemberOf(":$var", "e.groups"));
                $qb->setParameter($var, $id);
            }
            $sub->andWhere($orExpr);
        }

        $qb->andWhere($qb->expr()->exists($sub->getDQL()));
        return $qb->getQuery()->getResult();
    }
}
