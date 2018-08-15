<?php
declare(strict_types=1);

namespace App\Model\CMS;

use App\Model\ACL\Role;
use App\Model\CMS\Content\Content;
use App\Model\Page\PageException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\Entities\Attributes\Identifier;


/**
 * Entita stránky.
 *
 * @author Michal Májský
 * @author Jan Staněk <jan.stanek@skaut.cz>
 * @ORM\Entity(repositoryClass="PageRepository")
 * @ORM\Table(name="page")
 */
class Page
{
    use Identifier;

    /**
     * Název stránky.
     * @ORM\Column(type="string")
     * @var string
     */
    protected $name;

    /**
     * Cesta stránky.
     * @ORM\Column(type="string", unique=true)
     * @var string
     */
    protected $slug;

    /**
     * Pořadí v menu.
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $position = 0;

    /**
     * Viditelná.
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected $public = FALSE;

    /**
     * Role, které mají na stránku přístup.
     * @ORM\ManyToMany(targetEntity="\App\Model\ACL\Role", inversedBy="pages")
     * @var Collection
     */
    protected $roles;

    /**
     * Obsahy na stránce.
     * @ORM\OneToMany(targetEntity="\App\Model\CMS\Content\Content", mappedBy="page", cascade={"persist"})
     * @ORM\OrderBy({"position" = "ASC"})
     * @var Collection
     */
    protected $contents;


    /**
     * Page constructor.
     * @param string $name
     * @param string $slug
     */
    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->roles = new ArrayCollection();
        $this->contents = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     */
    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    /**
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @param int $position
     */
    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * @return bool
     */
    public function isPublic(): bool
    {
        return $this->public;
    }

    /**
     * @param bool $public
     */
    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    /**
     * @return Collection
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    /**
     * @return string
     */
    public function getRolesText(): string
    {
        return implode(', ', $this->roles->map(function (Role $role) {return $role->getName();})->toArray());
    }

    /**
     * @param Collection $roles
     */
    public function setRoles(Collection $roles): void
    {
        $this->roles->clear();
        foreach ($roles as $role)
            $this->roles->add($role);
    }

    /**
     * @param Role $role
     */
    public function addRole(Role $role): void
    {
        $this->roles->add($role);
    }

    /**
     * Vrací obsahy v oblasti.
     * @param null|string $area
     * @return Collection
     * @throws PageException
     */
    public function getContents(?string $area = NULL): Collection
    {
        if ($area === NULL)
            return $this->contents;
        if (!in_array($area, Content::$areas))
            throw new PageException("Area {$area} not defined.");
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('area', $area))
            ->orderBy(['position' => 'ASC']);
        return $this->contents->matching($criteria);
    }

    /**
     * Má stránka nějaký obsah v oblasti?
     * @param $area
     * @return bool
     * @throws PageException
     */
    public function hasContents(string $area): bool
    {
        if (!in_array($area, Content::$areas))
            throw new PageException("Area {$area} not defined.");
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('area', $area));
        return !$this->contents->matching($criteria)->isEmpty();
    }

    /**
     * Je stránka viditelná pro uživatele v rolích?
     * @param $roleNames
     * @return bool
     */
    public function isAllowedForRoles(array $roleNames): bool
    {
        foreach ($roleNames as $roleName) {
            foreach ($this->roles as $role) {
                if ($roleName == $role->getName())
                    return TRUE;
            }
        }
        return FALSE;
    }
}
