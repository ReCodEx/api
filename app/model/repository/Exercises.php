<?php

namespace App\Model\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseTag;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\User;
use App\Model\Entity\GroupMembership;
use App\Helpers\Pagination;
use App\Model\Helpers\PaginationDbHelper;
use App\Security\Roles;

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
     * @param User $user currently logged in (so we can restrict the selection based on user privileges)
     * @return Exercise[]
     */
    public function getPreparedForPagination(Pagination $pagination, Groups $groups, User $user = null): array
    {
        // Welcome to Doctrine HELL! Put your sickbags on standby!

        $qb = $this->createQueryBuilder('e'); // takes care of softdelete cases

        if ($pagination->hasFilter("archived")) {
            // archived == false -> show only archived
            if (!$pagination->getFilter("archived")) {
                $qb->andWhere($qb->expr()->isNotNull('e.archivedAt'));
            }
            // archived == true -> show everything (no condition added)
        } else {
            // no archived filter -> show only regular exercises
            $qb->andWhere($qb->expr()->isNull('e.archivedAt'));
        }

        // Filter by instance Id (through group membership) ...
        if ($pagination->hasFilter("instanceId")) {
            $instanceId = trim($pagination->getFilter("instanceId"));

            $instanceSub = $groups->createQueryBuilder("g");
            $instanceSub->andWhere($qb->expr()->eq("g.instance", $qb->expr()->literal($instanceId)))
                ->andWhere($instanceSub->expr()->isMemberOf("g", "e.groups"));

            $qb->andWhere($qb->expr()->exists($instanceSub->getDQL()));
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
        } elseif ($user && $user->getRole() !== Roles::SUPERADMIN_ROLE) {
            // This is mere performance optimization, but we are overlapping into ACLs here!
            // If the exercise.viewDetail ACL rules change significantly, this may need revision!
            // Main idea: do the checking of user-memberships on groups of residence all together.

            // find all groups of residence of all exercise
            $groupsOfResidence = $groups->findExerciseGroupsOfResidence($this); // true = only IDs

            // prepare member of index (all groups where the user is a member of a subgroup)
            $memberOf = $user->getGroups(null, GroupMembership::TYPE_STUDENT); // except student membership
            $memberOfClosure = $groups->groupsAncestralClosure($memberOf);
            $memberOfIndex = BaseRepository::createIdIndex($memberOfClosure, true); // all values are "true"

            // prepare index of groups where the user is directly admin
            $adminOf = $user->getGroups(GroupMembership::TYPE_ADMIN); // primary admin
            $adminOfIndex = BaseRepository::createIdIndex($adminOf, true); // all values are "true"

            // filter the groups of residence using membeship filters
            $filteredGroupsOfResidence = array_filter(
                $groupsOfResidence,
                function ($group) use ($memberOfIndex, $adminOfIndex) {
                    if (!empty($memberOfIndex[$group->getId()])) {
                        return true; // covered by membership from beneath
                    }

                    // check whether the group or its ancestors are covered by admin relation
                    while ($group && empty($adminOfIndex[$group->getId()])) {
                        $group = $group->getParentGroup();
                    }

                    return (bool)$group; // admin-ed parent group found -> the original group should be kept in the res
                }
            );

            // The exercise must be resident in one of the groups that passed user-filtering...
            $orExpr = $qb->expr()->orX();
            $gcounter = 0;
            foreach ($filteredGroupsOfResidence as $id) {
                $var = "residence" . ++$gcounter;
                $orExpr->add($qb->expr()->isMemberOf(":$var", "e.groups"));
                $qb->setParameter($var, $id);
            }

            // ...or the user is author of the exercise...
            $orExpr->add($qb->expr()->eq('e.author', ':author'));
            $qb->setParameter(':author', $user->getId());

            // ...or the exercise is globally public.
            $emptySub = $groups->createQueryBuilder("g2");
            $emptySub->where($emptySub->expr()->isMemberOf("g2", "e.groups"));
            $andExpr = $qb->expr()->andX()->add($qb->expr()->eq('e.isPublic', true))
                ->add($qb->expr()->not($qb->expr()->exists($emptySub->getDQL()))); // no attached groups
            $orExpr->add($andExpr);

            // seal the deal
            $qb->andWhere($orExpr);
        }

        // Only exercises with given tags
        if ($pagination->hasFilter("tags")) {
            $tagNames = $pagination->getFilter("tags");
            if (!is_array($tagNames)) {
                $tagNames = [$tagNames];
            }

            $tagSub = $qb->getEntityManager()->createQueryBuilder()->select("tags")->from(ExerciseTag::class, "tags");
            $tagSub->andWhere($qb->expr()->eq("tags.exercise", "e.id")); // only tags of examined exercise
            $tagSub->andWhere($tagSub->expr()->in("tags.name", $tagNames)); // at least one tag has to match
            $qb->andWhere($qb->expr()->exists($tagSub->getDQL()));
        }

        // Only exercises of specific RTEs (at least one RTE is present)
        if ($pagination->hasFilter("runtimeEnvironments", true)) { // true = not empty
            $this->getPreparedForPaginationEnvsFilter($qb, $pagination->getFilter("runtimeEnvironments"));
        }

        if ($pagination->getOrderBy() === "name") {
            // Special patch, we need to load localized names from another entity ...
            // Note: This requires custom COALESCE_SUB, which is actually normal
            // COALESCE function that allows subqueries inside in DQL
            $qb->addSelect(
                'COALESCE_SUB(
                    (SELECT le1.name FROM App\Model\Entity\LocalizedExercise AS le1
                        WHERE le1 MEMBER OF e.localizedTexts AND le1.locale = :locale),
                    (SELECT le2.name FROM App\Model\Entity\LocalizedExercise AS le2
                        WHERE le2 MEMBER OF e.localizedTexts AND le2.locale = \'en\'),
                    (SELECT MAX(le3.name) FROM App\Model\Entity\LocalizedExercise AS le3
                        WHERE le3 MEMBER OF e.localizedTexts)
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
    public function getAuthors(?string $instanceId, ?string $groupId, Groups $groups): array
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

    /**
     * Get all exercises that have given pipeline in the configuration.
     * @param string $pipelineId
     * @return Exercise[]
     */
    public function getPipelineExercises(string $pipelineId): array
    {
        // select for all the configs with the pipeline
        $sub = $this->em->createQueryBuilder()->select("ec")->from(ExerciseConfig::class, "ec");
        $sub->andWhere("ec = e.exerciseConfig");
        $sub->andWhere("ec.config LIKE :like");

        // select the exercises corresponding to those configs
        $qb = $this->createQueryBuilder('e'); // takes care of softdelete cases
        $qb->andWhere($qb->expr()->exists($sub->getDQL()));
        $qb->andWhere($qb->expr()->isNull('e.archivedAt'));
        $qb->setParameter("like", "%$pipelineId%");
        return $qb->getQuery()->getResult();
    }
}
