<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\BadRequestException;
use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\Model\Entity\GroupMembership;
use App\Model\Entity\CommentThread;
use App\Model\Repository\Users;
use App\V1Module\Presenters\AssignmentsPresenter;
use App\V1Module\Presenters\CommentsPresenter;
use App\V1Module\Presenters\GroupsPresenter;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\LocalImmutableFile;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

// A hack that should fool the request wrapper to use this address as remote address.
$_SERVER['REMOTE_ADDR'] = '2001:db8::1428:57ab';

/**
 * @testCase
 */
class UserLocking extends Tester\TestCase
{
    private $studentLogin = "submitUser1@example.com";
    private $studentPassword = "password";
    private $student2Login = "demoGroupMember1@example.com";

    private $ip = '2001:0db8:0:0::1428:57ab'; // must be compatible with what's in $_SERVER['REMOTE_ADDR']


    /** @var GroupsPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);

        // patch container, since we cannot create actual file storage manarer
        $fsName = current($this->container->findByType(FileStorageManager::class));
        $this->container->removeService($fsName);
        $this->container->addService($fsName, new FileStorageManager(
            Mockery::mock(LocalFileStorage::class),
            Mockery::mock(LocalHashFileStorage::class),
            Mockery::mock(TmpFilesHelper::class),
            ""
        ));
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        PresenterTestHelper::login($this->container, $this->studentLogin);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, GroupsPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    private function getAllGroupsInDepth($depth, $filter = null, $root = null)
    {
        if (!$root) {
            $rootCandidates = array_filter(
                $this->presenter->groups->findAll(),
                function (Group $g) {
                    return $g->getParentGroup() === null;
                }
            );
            Assert::count(1, $rootCandidates);
            $root = reset($rootCandidates);
        }

        if ($depth === 0) {
            if ($filter) {
                return $filter($root) ? [$root] : [];
            } else {
                return [$root];
            }
        }

        $res = [];
        foreach ($root->getChildGroups() as $child) {
            $res = array_merge($res, $this->getAllGroupsInDepth($depth - 1, $filter, $child));
        }
        return $res;
    }

    private function prepExamGroup($student, $relBegin = null, $relEnd = null, $strict = true): Group
    {
        $groups = $this->getAllGroupsInDepth(
            2,
            function (Group $g) {
                return !$g->isArchived();
            }
        );
        Assert::count(1, $groups);
        $group = $groups[0];
        $student->makeStudentOf($group);

        if ($relBegin !== null && $relEnd !== null) {
            $now = (new DateTime())->getTimestamp();
            $group->setExamPeriod(
                DateTime::createFromFormat('U', $now + $relBegin),
                DateTime::createFromFormat('U', $now + $relEnd),
                $strict
            );
        }

        $this->presenter->groups->persist($group);
        return $group;
    }

    public function testStudentLocksInGroup()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600); // exam in progress

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'lockStudent', 'id' => $group->getId(), 'userId' => $student->getId()]
        );
        $this->presenter->users->refresh($student);

        Assert::count(2, $payload);
        Assert::true(array_key_exists("user", $payload));
        Assert::true(array_key_exists("group", $payload));

        Assert::equal($student->getId(), $payload['user']['id']);
        Assert::equal($group->getId(), $payload['user']['privateData']['groupLock']);
        Assert::equal($_SERVER['REMOTE_ADDR'], $payload['user']['privateData']['ipLock']);
        Assert::equal($group->getExamEnd()->getTimestamp(), $payload['user']['privateData']['groupLockExpiration']);
        Assert::equal($group->getExamEnd()->getTimestamp(), $payload['user']['privateData']['ipLockExpiration']);
        Assert::equal($group->getId(), $payload['group']['id']);

        Assert::true($student->isIpLocked());
        Assert::true($student->verifyIpLock($this->ip));
        Assert::true($student->isGroupLocked());
        Assert::equal($group->getId(), $student->getGroupLock()->getId());

        // and the group exam entity was created
        $groupExam = $this->presenter->groupExams->findBy(["group" => $group, "begin" => $group->getExamBegin()]);
        Assert::count(1, $groupExam);
        Assert::equal($group->getExamEnd()->getTimestamp(), $groupExam[0]->getEnd()->getTimestamp());
        Assert::equal($group->isExamLockStrict(), $groupExam[0]->isLockStrict());
    }

    public function testSecondStudentLocksInGroup()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600); // exam in progress

        // create group exam simulates situation where some previous student locked in
        $groupExam = $this->presenter->groupExams->findOrCreate($group);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'lockStudent', 'id' => $group->getId(), 'userId' => $student->getId()]
        );
        $this->presenter->users->refresh($student);

        Assert::count(2, $payload);
        Assert::true(array_key_exists("user", $payload));
        Assert::true(array_key_exists("group", $payload));

        Assert::equal($student->getId(), $payload['user']['id']);
        Assert::equal($group->getId(), $payload['user']['privateData']['groupLock']);
        Assert::equal($_SERVER['REMOTE_ADDR'], $payload['user']['privateData']['ipLock']);
        Assert::equal($group->getExamEnd()->getTimestamp(), $payload['user']['privateData']['groupLockExpiration']);
        Assert::equal($group->getExamEnd()->getTimestamp(), $payload['user']['privateData']['ipLockExpiration']);
        Assert::equal($group->getId(), $payload['group']['id']);

        Assert::true($student->isIpLocked());
        Assert::true($student->verifyIpLock($this->ip));
        Assert::true($student->isGroupLocked());
        Assert::equal($group->getId(), $student->getGroupLock()->getId());
    }

    public function testStudentLockBeforeExamFails()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, 3600, 7200); // exam in future
        Assert::exception(
            function () use ($group, $student) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'POST',
                    ['action' => 'lockStudent', 'id' => $group->getId(), 'userId' => $student->getId()]
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testStudentLockAfterExamFails()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -7200, -3600); // exam in past
        Assert::exception(
            function () use ($group, $student) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'POST',
                    ['action' => 'lockStudent', 'id' => $group->getId(), 'userId' => $student->getId()]
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testStudentLockNoExamFails()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student); // no exam
        Assert::exception(
            function () use ($group, $student) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'POST',
                    ['action' => 'lockStudent', 'id' => $group->getId(), 'userId' => $student->getId()]
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testLocksExpiration()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);
        $exp = new DateTime();
        $exp->modify("-1 hour"); // already expired
        $student->setIpLock($this->ip, $exp);
        $student->setGroupLock($group, $exp, $group->isExamLockStrict());
        Assert::false($student->isIpLocked());
        Assert::true($student->verifyIpLock('127.0.0.1'));
        Assert::false($student->isGroupLocked());
    }

    public function testRemoveLockByTeacher()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);

        PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'lockStudent', 'id' => $group->getId(), 'userId' => $student->getId()]
        );

        $lock = $this->presenter->groupExamLocks->getCurrentLock($student);
        Assert::truthy($lock);
        Assert::null($lock->getUnlockedAt());

        PresenterTestHelper::loginDefaultAdmin($this->container);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'DELETE',
            ['action' => 'unlockStudent', 'id' => $group->getId(), 'userId' => $student->getId()]
        );
        $this->presenter->users->refresh($student);
        $this->presenter->groupExamLocks->refresh($lock);

        Assert::equal($student->getId(), $payload['id']);
        Assert::null($payload['privateData']['groupLock']);
        Assert::null($payload['privateData']['ipLock']);
        Assert::false($student->isIpLocked());
        Assert::true($student->verifyIpLock('127.0.0.1'));
        Assert::false($student->isGroupLocked());
        Assert::truthy($lock->getUnlockedAt());
    }

    public function testRemoveLockByTeacherNoLockFail()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);
        PresenterTestHelper::loginDefaultAdmin($this->container);

        Assert::exception(
            function () use ($group, $student) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'DELETE',
                    ['action' => 'unlockStudent', 'id' => $group->getId(), 'userId' => $student->getId()]
                );
            },
            App\Exceptions\InvalidApiArgumentException::class
        );
    }

    public function testStudentUnlockFails()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        Assert::exception(
            function () use ($group, $student) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'DELETE',
                    ['action' => 'unlockStudent', 'id' => $group->getId(), 'userId' => $student->getId()]
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testLockedUserCanSeeExamAssignments()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'assignments', 'id' => $group->getId()]
        );
        Assert::count(2, $payload);
    }

    public function testNotLockedUserCannotSeeExamAssignments()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'assignments', 'id' => $group->getId()]
        );
        Assert::count(1, $payload);
        Assert::false($payload[0]["isExam"]);
    }

    public function testAllAssignmentsAreVisibleIfNoExam()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'assignments', 'id' => $group->getId()]
        );
        Assert::count(2, $payload);
    }

    public function testAllAssignmentsAreVisibleIfExamInFuture()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, 3600, 7200);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'assignments', 'id' => $group->getId()]
        );
        Assert::count(2, $payload);
    }

    public function testLockedUserCanSeeSolutions()
    {
        PresenterTestHelper::login($this->container, $this->student2Login);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, AssignmentsPresenter::class);

        $student = $this->presenter->users->getByEmail($this->student2Login);
        $group = $this->prepExamGroup($student, -3600, 3600);
        $assignments = $group->getAssignments()->filter(function ($a) {
            return count($a->getAssignmentSolutions()) > 0;
        });
        Assert::count(1, $assignments);
        $assignment = $assignments->toArray()[0];

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'userSolutions', 'id' => $assignment->getId(), 'userId' => $student->getId()]
        );
        Assert::count(1, $payload);
    }

    public function testStrictLockedUserCannotSeeAssignmentsInOtherGroups()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600, true); // true = strict

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        Assert::exception(
            function () use ($group, $student) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'GET',
                    ['action' => 'assignments', 'id' => $group->getParentGroup()->getId()]
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testLockedUserCanSeeAssignmentsInOtherGroups()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600, false); // false = not strict

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'assignments', 'id' => $group->getParentGroup()->getId()]
        );
        Assert::count(1, $payload);
    }

    public function testStrictLockedUserCannotSeeSolutionsInOtherGroups()
    {
        $this->presenter = PresenterTestHelper::createPresenter($this->container, AssignmentsPresenter::class);
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600, true); // true = strict

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        $group = $group->getParentGroup();
        $assignments = $group->getAssignments();
        Assert::count(1, $assignments);
        $assignment = $assignments->toArray()[0];

        Assert::exception(
            function () use ($assignment, $student) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'GET',
                    ['action' => 'userSolutions', 'id' => $assignment->getId(), 'userId' => $student->getId()]
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testLockedUserCanSeeSolutionsInOtherGroups()
    {
        $this->presenter = PresenterTestHelper::createPresenter($this->container, AssignmentsPresenter::class);
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600, false); // false = not strict

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        $group = $group->getParentGroup();
        $assignments = $group->getAssignments();
        Assert::count(1, $assignments);
        $assignment = $assignments->toArray()[0];

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'userSolutions', 'id' => $assignment->getId(), 'userId' => $student->getId()]
        );
        Assert::count(4, $payload);
    }

    public function testIpLockPrevetsOtherIps()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);

        // unexpected IP
        $student->setIpLock('192.168.42.54', $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        Assert::exception(
            function () use ($group, $student) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'GET',
                    ['action' => 'assignments', 'id' => $group->getId()]
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testLockedUserCannotComment()
    {
        PresenterTestHelper::login($this->container, $this->student2Login);
        $student = $this->presenter->users->getByEmail($this->student2Login);
        $group = $this->prepExamGroup($student, -3600, 3600, false);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, CommentsPresenter::class);

        $assignments = $group->getAssignments()->filter(function ($a) {
            return count($a->getAssignmentSolutions()) > 0;
        });
        Assert::count(1, $assignments);
        $assignment = $assignments->toArray()[0];

        $solutions = $assignment->getAssignmentSolutions();
        Assert::count(1, $solutions);
        $solution = $solutions->toArray()[0];

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        Assert::exception(
            function () use ($solution) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Comments',
                    'POST',
                    ['action' => 'addComment', 'id' => $solution->getId()],
                    ['text' => 'some comment text', 'isPrivate' => 'false']
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testLockedUserCannotCommentInExistingThread()
    {
        PresenterTestHelper::login($this->container, $this->student2Login);
        $student = $this->presenter->users->getByEmail($this->student2Login);
        $group = $this->prepExamGroup($student, -3600, 3600, false);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, CommentsPresenter::class);

        $assignments = $group->getAssignments()->filter(function ($a) {
            return count($a->getAssignmentSolutions()) > 0;
        });
        Assert::count(1, $assignments);
        $assignment = $assignments->toArray()[0];

        $solutions = $assignment->getAssignmentSolutions();
        Assert::count(1, $solutions);
        $solution = $solutions->toArray()[0];

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        $thread = CommentThread::createThread($solution->getId());
        $this->presenter->comments->persist($thread, false);

        Assert::exception(
            function () use ($solution) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Comments',
                    'POST',
                    ['action' => 'addComment', 'id' => $solution->getId()],
                    ['text' => 'some comment text', 'isPrivate' => 'false']
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testLockedUserCannotCommentAssignment()
    {
        PresenterTestHelper::login($this->container, $this->student2Login);
        $student = $this->presenter->users->getByEmail($this->student2Login);
        $group = $this->prepExamGroup($student, -3600, 3600, false);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, CommentsPresenter::class);

        $assignments = $group->getAssignments();
        Assert::count(2, $assignments);
        $assignment = $assignments->toArray()[0];

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        Assert::exception(
            function () use ($assignment) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Comments',
                    'POST',
                    ['action' => 'addComment', 'id' => $assignment->getId()],
                    ['text' => 'some comment text', 'isPrivate' => 'false']
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testLockedUserCannotCommentAssignmentInExistingThread()
    {
        PresenterTestHelper::login($this->container, $this->student2Login);
        $student = $this->presenter->users->getByEmail($this->student2Login);
        $group = $this->prepExamGroup($student, -3600, 3600, false);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, CommentsPresenter::class);

        $assignments = $group->getAssignments();
        Assert::count(2, $assignments);
        $assignment = $assignments->toArray()[0];

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);

        $thread = CommentThread::createThread($assignment->getId());
        $this->presenter->comments->persist($thread, false);

        Assert::exception(
            function () use ($assignment) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Comments',
                    'POST',
                    ['action' => 'addComment', 'id' => $assignment->getId()],
                    ['text' => 'some comment text', 'isPrivate' => 'false']
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testExamUpdateSyncLockedStudentExpiration()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $student->setIpLock($this->ip, $group->getExamEnd());
        $student->setGroupLock($group, $group->getExamEnd(), $group->isExamLockStrict());
        $this->presenter->users->persist($student);
        Assert::same($group->getExamEnd()->getTimestamp(), $student->getGroupLockExpiration()?->getTimestamp());

        $now = (new DateTime())->getTimestamp();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'setExamPeriod', 'id' => $group->getId()],
            ['end' => $now]
        );

        $this->presenter->users->refresh($student);
        Assert::same($now, $student->getGroupLockExpiration()?->getTimestamp());
    }

    public function testExamAssignmentSyncDeadline()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $group->getAssignments()->filter(function ($a) {
            return $a->isExam();
        });
        Assert::count(1, $assignments);
        $assignment = array_values(($assignments->toArray()))[0];

        // sync deadline with exam end
        $assignment->setFirstDeadline($group->getExamEnd());
        $this->presenter->assignments->persist($assignment);

        $now = (new DateTime())->getTimestamp();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'setExamPeriod', 'id' => $group->getId()],
            ['end' => $now]
        );
        Assert::null($payload['privateData']['examBegin']);
        Assert::null($payload['privateData']['examEnd']);
        $this->presenter->assignments->refresh($assignment);
        Assert::equal($now, $assignment->getFirstDeadline()->getTimestamp()); // deadline was synced
    }

    public function testExamAssignmentNotSyncDeadline()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, -3600, 3600);
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $group->getAssignments()->filter(function ($a) {
            return $a->isExam();
        });
        Assert::count(1, $assignments);
        $assignment = array_values(($assignments->toArray()))[0];

        $now = new DateTime();
        $assignment->setFirstDeadline($now);
        $this->presenter->assignments->persist($assignment);

        $end = (new DateTime())->getTimestamp() + 1800;

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'setExamPeriod', 'id' => $group->getId()],
            ['end' => $end]
        );
        Assert::equal($end, $payload['privateData']['examEnd']);
        $this->presenter->assignments->refresh($assignment);
        Assert::equal($now->getTimestamp(), $assignment->getFirstDeadline()->getTimestamp()); // deadline was not synced
    }

    public function testExamAssignmentSyncVisibleFrom()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, 3600, 7200);
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $group->getAssignments()->filter(function ($a) {
            return $a->isExam();
        });
        Assert::count(1, $assignments);
        $assignment = array_values(($assignments->toArray()))[0];

        // sync visible from with exam begin
        $assignment->setVisibleFrom($group->getExamBegin());
        $this->presenter->assignments->persist($assignment);

        $begin = (new DateTime())->getTimestamp() + 1000;

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'setExamPeriod', 'id' => $group->getId()],
            ['begin' => $begin, 'end' => $group->getExamEnd()->getTimestamp()]
        );
        Assert::equal($begin, $payload['privateData']['examBegin']);
        $this->presenter->assignments->refresh($assignment);
        Assert::equal($begin, $assignment->getVisibleFrom()->getTimestamp()); // visible from was synced
    }

    public function testExamAssignmentSyncVisibleFromNow()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, 3600, 7200);
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $group->getAssignments()->filter(function ($a) {
            return $a->isExam();
        });
        Assert::count(1, $assignments);
        $assignment = array_values(($assignments->toArray()))[0];

        // sync visible from with exam begin
        $assignment->setVisibleFrom($group->getExamBegin());
        $this->presenter->assignments->persist($assignment);

        $begin = (new DateTime())->getTimestamp();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'setExamPeriod', 'id' => $group->getId()],
            ['begin' => $begin, 'end' => $group->getExamEnd()->getTimestamp()]
        );
        Assert::equal($begin, $payload['privateData']['examBegin']);
        $this->presenter->assignments->refresh($assignment);
        Assert::null($assignment->getVisibleFrom()); // visible from was erased
    }

    public function testExamAssignmentNotSyncVisibleFrom()
    {
        $student = $this->presenter->users->getByEmail($this->studentLogin);
        $group = $this->prepExamGroup($student, 3600, 7200);
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $group->getAssignments()->filter(function ($a) {
            return $a->isExam();
        });
        Assert::count(1, $assignments);
        $assignment = array_values(($assignments->toArray()))[0];

        // sync visible from with exam begin
        $assignment->setVisibleFrom($group->getExamEnd());
        $this->presenter->assignments->persist($assignment);

        $begin = (new DateTime())->getTimestamp() + 1000;

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'setExamPeriod', 'id' => $group->getId()],
            ['begin' => $begin, 'end' => $group->getExamEnd()->getTimestamp()]
        );
        Assert::equal($begin, $payload['privateData']['examBegin']);
        $this->presenter->assignments->refresh($assignment);
        Assert::equal($group->getExamEnd()->getTimestamp(), $assignment->getVisibleFrom()->getTimestamp());
    }
}

$testCase = new UserLocking();
$testCase->run();
