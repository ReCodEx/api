<?php

namespace App\Model\Entity;

use App\Helpers\Localizations;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class Instance
{
    use UpdateableEntity;
    use DeleteableEntity;
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isOpen;

    /**
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isAllowed;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $admin;

    public function getAdmin(): ?User
    {
        return $this->admin->isDeleted() ? null : $this->admin;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $needsLicence;

    /**
     * @ORM\ManyToOne(targetEntity="Group", cascade={"persist"})
     * @var Group
     */
    protected $rootGroup;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="Licence", mappedBy="instance")
     */
    protected $licences;

    public function addLicence(Licence $licence)
    {
        $this->licences->add($licence);
    }

    public function getValidLicences()
    {
        return $this->licences->filter(
            function (Licence $licence) {
                return $licence->isValid();
            }
        );
    }

    public function hasValidLicence()
    {
        return $this->needsLicence === false || $this->getValidLicences()->count() > 0;
    }

    /**
     * @ORM\ManyToMany(targetEntity="User", mappedBy="instances")
     */
    protected $members;

    public function addMember(User $user)
    {
        $this->members->add($user);
    }

    /**
     * @ORM\OneToMany(targetEntity="Group", mappedBy="instance", cascade={"persist"})
     */
    protected $groups;

    public function addGroup(Group $group)
    {
        $this->groups->add($group);
    }

    public function getGroups(): Collection
    {
        return $this->groups->filter(
            function (Group $group) {
                return $group->getDeletedAt() === null;
            }
        );
    }

    public function __construct()
    {
        $this->licences = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->members = new ArrayCollection();
    }

    public function getName()
    {
        /** @var LocalizedGroup $localizedRootGroup */
        $localizedRootGroup = Localizations::getPrimaryLocalization($this->rootGroup->getLocalizedTexts());
        return $localizedRootGroup->getName();
    }

    public static function createInstance(array $localizedTexts, bool $isOpen, User $admin = null)
    {
        $instance = new Instance();
        $instance->isOpen = $isOpen;
        $instance->isAllowed = true; //@todo - find out who should set this and how
        $instance->needsLicence = true;
        $now = new \DateTime();
        $instance->createdAt = $now;
        $instance->updatedAt = $now;
        $instance->admin = $admin;

        // now create the root group for the instance
        $instance->rootGroup = new Group(
            "",
            $instance,
            $admin,
            null,
            false,
            true
        );

        /** @var LocalizedGroup $text */
        foreach ($localizedTexts as $text) {
            $instance->rootGroup->addLocalizedText($text);
        }

        return $instance;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRootGroup(): ?Group
    {
        return $this->rootGroup;
    }

    public function isAllowed(): bool
    {
        return $this->isAllowed;
    }

    public function setAdmin(User $admin): void
    {
        $this->admin = $admin;
    }

    public function setIsOpen(bool $isOpen): void
    {
        $this->isOpen = $isOpen;
    }

    public function getLicences(): Collection
    {
        return $this->licences;
    }

    public function setNeedsLicence(bool $needsLicence): void
    {
        $this->needsLicence = $needsLicence;
    }
}
