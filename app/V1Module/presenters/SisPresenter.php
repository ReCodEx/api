<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Exceptions\ApiException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\SisCourseRecord;
use App\Helpers\SisHelper;
use App\Model\Entity\Group;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\SisGroupBinding;
use App\Model\Entity\SisValidTerm;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Groups;
use App\Model\Repository\Instances;
use App\Model\Repository\SisGroupBindings;
use App\Model\Repository\SisValidTerms;
use App\Model\View\GroupViewFactory;
use App\Security\ACL\ISisPermissions;
use App\Security\ACL\SisGroupContext;
use App\Security\ACL\SisIdWrapper;
use DateTime;
use Exception;

/**
 * @LoggedIn
 */
class SisPresenter extends BasePresenter
{
    /**
     * @var SisHelper
     * @inject
     */
    public $sisHelper;

    /**
     * @var ExternalLogins
     * @inject
     */
    public $externalLogins;

    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var Instances
     * @inject
     */
    public $instances;

    /**
     * @var SisGroupBindings
     * @inject
     */
    public $sisGroupBindings;

    /**
     * @var SisValidTerms
     * @inject
     */
    public $sisValidTerms;

    /**
     * @var GroupViewFactory
     * @inject
     */
    public $groupViewFactory;

    /**
     * @var ISisPermissions
     * @inject
     */
    public $sisAcl;


    /**
     * @GET
     * @throws ForbiddenRequestException
     */
    public function actionStatus()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetTerms()
    {
        if (!$this->sisAcl->canViewTerms()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of all registered SIS terms
     * @GET
     */
    public function actionGetTerms()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRegisterTerm()
    {
        if (!$this->sisAcl->canCreateTerm()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Register a new term
     * @POST
     * @throws InvalidApiArgumentException
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     */
    #[Post("year", new VMixed(), nullable: true)]
    #[Post("term", new VMixed(), nullable: true)]
    public function actionRegisterTerm()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckEditTerm(string $id)
    {
        $term = $this->sisValidTerms->findOrThrow($id);

        if (!$this->sisAcl->canEditTerm($term)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Set details of a term
     * @POST
     * @throws InvalidApiArgumentException
     * @throws NotFoundException
     */
    #[Post("beginning", new VTimestamp())]
    #[Post("end", new VTimestamp())]
    #[Post("advertiseUntil", new VTimestamp())]
    #[Path("id", new VString(), required: true)]
    public function actionEditTerm(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteTerm(string $id)
    {
        $term = $this->sisValidTerms->findOrThrow($id);
        if (!$this->sisAcl->canDeleteTerm($term)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Delete a term
     * @DELETE
     * @throws NotFoundException
     */
    #[Path("id", new VString(), required: true)]
    public function actionDeleteTerm(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSubscribedGroups($userId, $year, $term)
    {
        $user = $this->users->findOrThrow($userId);
        $sisUserId = $this->getSisUserIdOrThrow($user);

        if (!$this->sisAcl->canViewCourses(new SisIdWrapper($sisUserId))) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all courses subscirbed by a student and corresponding ReCodEx groups.
     * Organizational and archived groups are filtered out from the result.
     * Each course holds bound group IDs and group objects are returned in a separate array.
     * Whole ancestral closure of groups is returned, so the webapp may properly assemble hiarichial group names.
     * @GET
     * @throws InvalidApiArgumentException
     * @throws BadRequestException
     */
    #[Path("userId", new VString(), required: true)]
    #[Path("year", new VInt(), required: true)]
    #[Path("term", new VInt(), required: true)]
    public function actionSubscribedCourses($userId, $year, $term)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSupervisedCourses($userId, $year, $term)
    {
        $user = $this->users->findOrThrow($userId);
        $sisUserId = $this->getSisUserIdOrThrow($user);

        if (!$this->sisAcl->canViewCourses(new SisIdWrapper($sisUserId))) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get supervised SIS courses and corresponding ReCodEx groups.
     * Each course holds bound group IDs and group objects are returned in a separate array.
     * Whole ancestral closure of groups is returned, so the webapp may properly assemble hiarichial group names.
     * @GET
     * @throws InvalidApiArgumentException
     * @throws NotFoundException
     * @throws BadRequestException
     */
    #[Path("userId", new VString(), required: true)]
    #[Path("year", new VInt(), required: true)]
    #[Path("term", new VInt(), required: true)]
    public function actionSupervisedCourses($userId, $year, $term)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * @param array $captions List of captions [ lang => caption ].
     * @param string $suffix Suffix expected to be appended to captions of all languages.
     * @param Group $parentGroup The uniqueness is ensured only amongst the siblings.
     * @return bool True if at least one caption is in conflict.
     */
    private function areCaptionsDuplicit(array $captions, string $suffix, Group $parentGroup)
    {
        foreach ($captions as $lang => $caption) {
            if (
                count(
                    $this->groups->findByName($lang, $caption . $suffix, $parentGroup->getInstance(), $parentGroup)
                ) > 0
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Make sure new group captions (in all languages) are unique.
     * If not, new unique names are created by appending value of a counter.
     * @param array $captions List of captions [ lang => caption ].
     * @param Group $parentGroup The uniqueness is ensured only amongst the siblings.
     */
    private function makeCaptionsUnique(array &$captions, Group $parentGroup)
    {
        // Trial and error approach to find suitable suffix to make the captions unique (starting with empty suffix).
        $counter = 1;
        $suffix = '';
        while ($this->areCaptionsDuplicit($captions, $suffix, $parentGroup)) {
            ++$counter;
            $suffix = " [$counter]";
        }

        // Acceptable suffix was found, lets append it...
        foreach ($captions as &$caption) {
            $caption .= $suffix;
        }
    }

    /**
     * Create a new group based on a SIS group
     * @POST
     * @throws BadRequestException
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     * @throws Exception
     */
    #[Post("parentGroupId", new VMixed(), nullable: true)]
    #[Path("courseId", new VString(), required: true)]
    public function actionCreateGroup($courseId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Bind an existing local group to a SIS group
     * @POST
     * @throws ApiException
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     */
    #[Post("groupId", new VMixed(), nullable: true)]
    #[Path("courseId", new VString(), required: true)]
    public function actionBindGroup($courseId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Delete a binding between a local group and a SIS group
     * @DELETE
     * @throws BadRequestException
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     * @throws NotFoundException
     */
    #[Path("courseId", new VString(), "an identifier of a SIS course", required: true)]
    #[Path("groupId", new VString(), "an identifier of a local group", required: true)]
    public function actionUnbindGroup($courseId, $groupId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Find groups that can be chosen as parents of a group created from given SIS group by current user
     * @GET
     * @throws ApiException
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     */
    #[Path("courseId", new VString(), required: true)]
    public function actionPossibleParents($courseId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * @throws BadRequestException
     */
    protected function getSisUserIdOrThrow(User $user)
    {
        $login = $this->externalLogins->findByUser($user, "cas-uk");

        if ($login === null) {
            throw new BadRequestException(sprintf("User %s is not bound to a CAS UK account", $user->getId()));
        }

        return $login->getExternalId();
    }

    /**
     * @param string $remoteGroupId
     * @param string $sisUserId
     * @return SisCourseRecord|mixed
     * @throws BadRequestException
     * @throws InvalidApiArgumentException
     */
    private function findRemoteCourseOrThrow($remoteGroupId, $sisUserId)
    {
        foreach ($this->sisValidTerms->findAll() as $term) {
            foreach ($this->sisHelper->getCourses($sisUserId, $term->getYear(), $term->getTerm()) as $course) {
                if ($course->getCode() === $remoteGroupId) {
                    return $course;
                }
            }
        }

        throw new BadRequestException(sprintf("Sis course %s was not found", $remoteGroupId));
    }

    private function dayToString($day, $language)
    {
        static $dayLabels = [
            'en' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'cs' => ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne']
        ];

        return $dayLabels[$language][$day];
    }

    private function oddWeeksToString($oddWeeks, $language)
    {
        static $labels = [
            'en' => ['Even weeks', 'Odd weeks'],
            'cs' => ['Sudé týdny', 'Liché týdny']
        ];

        return $labels[$language][$oddWeeks];
    }
}
