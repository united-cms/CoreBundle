<?php

namespace UnitedCMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use UnitedCMS\CoreBundle\Collection\Types\TableCollectionType;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\Accessor;
use UnitedCMS\CoreBundle\Security\ContentVoter;

use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use UnitedCMS\CoreBundle\Validator\Constraints\DefaultCollectionType;
use UnitedCMS\CoreBundle\Validator\Constraints\ReservedWords;
use UnitedCMS\CoreBundle\Validator\Constraints\ValidPermissions;

/**
 * ContentType
 *
 * @ORM\Table(name="content_type")
 * @ORM\Entity(repositoryClass="Gedmo\Sortable\Entity\Repository\SortableRepository")
 * @UniqueEntity(fields={"identifier", "domain"}, message="validation.identifier_already_taken")
 * @ExclusionPolicy("all")
 */
class ContentType implements Fieldable
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @Assert\NotBlank(message="validation.not_blank")
     * @Assert\Length(max="255", maxMessage="validation.too_long")
     * @ORM\Column(name="title", type="string", length=255)
     * @Expose
     */
    private $title;

    /**
     * @var string
     * @Assert\NotBlank(message="validation.not_blank")
     * @Assert\Length(max="255", maxMessage="validation.too_long")
     * @Assert\Regex(pattern="/^[a-z0-9_]+$/i", message="validation.invalid_characters")
     * @ReservedWords(message="validation.reserved_identifier", reserved="UnitedCMS\CoreBundle\Entity\ContentType::RESERVED_IDENTIFIERS")
     * @ORM\Column(name="identifier", type="string", length=255)
     * @Expose
     */
    private $identifier;

    /**
     * @var string
     * @ORM\Column(name="description", type="text", nullable=true)
     * @Expose
     */
    private $description;

    /**
     * @var string
     * @Assert\Length(max="255", maxMessage="validation.too_long")
     * @Assert\Regex(pattern="/^[a-z0-9_]+$/i", message="validation.invalid_characters")
     * @ORM\Column(name="icon", type="string", length=255, nullable=true)
     * @Expose
     */
    private $icon;

    /**
     * @var Domain
     * @Gedmo\SortableGroup
     * @Assert\NotBlank(message="validation.not_blank")
     * @ORM\ManyToOne(targetEntity="UnitedCMS\CoreBundle\Entity\Domain", inversedBy="contentTypes")
     */
    private $domain;

    /**
     * @var ContentTypeField[]
     * @Assert\Valid()
     * @Type("ArrayCollection<UnitedCMS\CoreBundle\Entity\ContentTypeField>")
     * @Accessor(getter="getFields",setter="setFields")
     * @ORM\OneToMany(targetEntity="UnitedCMS\CoreBundle\Entity\ContentTypeField", mappedBy="contentType", cascade={"persist", "remove", "merge"}, indexBy="identifier", orphanRemoval=true)
     * @ORM\OrderBy({"weight": "ASC"})
     * @Expose
     */
    private $fields;

    /**
     * @var Collection[]
     *
     * @Type("ArrayCollection<UnitedCMS\CoreBundle\Entity\Collection>")
     * @Assert\Valid()
     * @DefaultCollectionType(message="validation.missing_default_collection")
     * @Accessor(getter="getCollections",setter="setCollections")
     * @ORM\OneToMany(targetEntity="UnitedCMS\CoreBundle\Entity\Collection", mappedBy="contentType", cascade={"persist", "remove", "merge"}, indexBy="identifier", orphanRemoval=true)
     * @Expose
     */
    private $collections;

    /**
     * @var array
     * @ValidPermissions(callbackAttributes="allowedPermissionKeys", callbackRoles="allowedPermissionRoles", message="validation.invalid_selection")
     * @ORM\Column(name="permissions", type="array", nullable=true)
     * @Expose
     */
    private $permissions;

    /**
     * @var array
     * @Assert\All({
     *     @Assert\Locale(),
     *     @Assert\NotBlank()
     * })
     * @ORM\Column(name="locales", type="array", nullable=true)
     * @Type("array<string>")
     * @Expose
     */
    private $locales;

    /**
     * @var int
     * @Gedmo\SortablePosition
     * @ORM\Column(name="weight", type="integer")
     */
    private $weight;

    /**
     * @var Content[]|ArrayCollection
     * @Assert\Count(max="0", maxMessage="validation.should_be_empty", groups={"DELETE"})
     * @Type("ArrayCollection<UnitedCMS\CoreBundle\Entity\Content>")
     * @Assert\Valid()
     *
     * TODO: Checking that all the content is valid will become very expensive for large content sets. We most likely will need another approach.
     *
     * @ORM\OneToMany(targetEntity="UnitedCMS\CoreBundle\Entity\Content", mappedBy="contentType", fetch="EXTRA_LAZY")
     */
    private $content;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
        $this->collections = new ArrayCollection();
        $this->locales = [];
        $this->addDefaultCollection();
        $this->addDefaultPermissions();
    }

    public function __toString()
    {
        return ''.$this->title;
    }

    /**
     * Each ContentType must have a "all" collection. This function adds it to the ArrayCollection.
     */
    private function addDefaultCollection()
    {
        $defaultCollection = new Collection();
        $defaultCollection
            ->setType(TableCollectionType::getType())
            ->setTitle('All')
            ->setIdentifier(Collection::DEFAULT_COLLECTION_IDENTIFIER);
        $this->addCollection($defaultCollection);
    }

    private function addDefaultPermissions()
    {
        $this->permissions[ContentVoter::VIEW] = [Domain::ROLE_PUBLIC, Domain::ROLE_EDITOR, Domain::ROLE_ADMINISTRATOR];
        $this->permissions[ContentVoter::LIST] = [Domain::ROLE_PUBLIC, Domain::ROLE_EDITOR, Domain::ROLE_ADMINISTRATOR];
        $this->permissions[ContentVoter::CREATE] = [Domain::ROLE_EDITOR, Domain::ROLE_ADMINISTRATOR];
        $this->permissions[ContentVoter::UPDATE] = [Domain::ROLE_EDITOR, Domain::ROLE_ADMINISTRATOR];
        $this->permissions[ContentVoter::DELETE] = [Domain::ROLE_EDITOR, Domain::ROLE_ADMINISTRATOR];
    }

    public function allowedPermissionRoles(): array
    {
        if ($this->getDomain()) {
            return $this->getDomain()->getRoles();
        }

        return [];
    }

    public function allowedPermissionKeys(): array
    {
        return array_merge(ContentVoter::ENTITY_PERMISSIONS, ContentVoter::BUNDLE_PERMISSIONS);
    }

    /**
     * This function sets all structure fields from the given entity and calls setFromEntity for all updated
     * collections and fields.
     *
     * @param ContentType $contentType
     * @return ContentType
     */
    public function setFromEntity(ContentType $contentType)
    {
        $this
            ->setTitle($contentType->getTitle())
            ->setIdentifier($contentType->getIdentifier())
            ->setWeight($contentType->getWeight())
            ->setIcon($contentType->getIcon())
            ->setPermissions($contentType->getPermissions())
            ->setDescription($contentType->getDescription())
            ->setLocales($contentType->getLocales());

        // Fields to delete
        foreach (array_diff($this->getFields()->getKeys(), $contentType->getFields()->getKeys()) as $field) {
            $this->getFields()->remove($field);
        }

        // Fields to add
        foreach (array_diff($contentType->getFields()->getKeys(), $this->getFields()->getKeys()) as $field) {
            $this->addField($contentType->getFields()->get($field));
        }

        // Fields to update
        foreach (array_intersect($contentType->getFields()->getKeys(), $this->getFields()->getKeys()) as $field) {
            $this->getFields()->get($field)->setFromEntity($contentType->getFields()->get($field));
        }

        // Collections to delete
        foreach (array_diff(
                     $this->getCollections()->getKeys(),
                     $contentType->getCollections()->getKeys()
                 ) as $collection) {
            $this->getCollections()->remove($collection);
        }

        // Collections to add
        foreach (array_diff(
                     $contentType->getCollections()->getKeys(),
                     $this->getCollections()->getKeys()
                 ) as $collection) {
            $this->addCollection($contentType->getCollections()->get($collection));
        }

        // Collections to update
        foreach (array_intersect(
                     $contentType->getCollections()->getKeys(),
                     $this->getCollections()->getKeys()
                 ) as $collection) {
            $this->getCollections()->get($collection)->setFromEntity($contentType->getCollections()->get($collection));
        }

        return $this;
    }

    /**
     * Set id
     *
     * @param $id
     *
     * @return ContentType
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set title
     *
     * @param string $title
     *
     * @return ContentType
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set identifier
     *
     * @param string $identifier
     *
     * @return ContentType
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Get identifier
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return ContentType
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set icon
     *
     * @param string $icon
     *
     * @return ContentType
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get icon
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @return Domain
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param Domain $domain
     *
     * @return ContentType
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
        $domain->addContentType($this);

        return $this;
    }

    /**
     * @return ContentTypeField[]|ArrayCollection
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param ContentTypeField[]|ArrayCollection $fields
     *
     * @return ContentType
     */
    public function setFields($fields)
    {
        $this->fields->clear();
        foreach ($fields as $field) {
            $this->addField($field);
        }

        return $this;
    }

    /**
     * @param FieldableField $field
     *
     * @return ContentType
     */
    public function addField(FieldableField $field)
    {
        if (!$field instanceof ContentTypeField) {
            throw new \InvalidArgumentException("'$field' is not a ContentTypeField.");
        }

        if (!$this->fields->containsKey($field->getIdentifier())) {
            $this->fields->set($field->getIdentifier(), $field);
            $field->setContentType($this);
            $field->setWeight($this->fields->count() - 1);
        }

        return $this;
    }

    /**
     * @return Collection[]|ArrayCollection
     */
    public function getCollections()
    {
        return $this->collections;
    }

    /**
     * @param $key
     * @return Collection
     */
    public function getCollection($key)
    {
        foreach ($this->getCollections() as $collection) {
            if ($collection->getIdentifier() === $key) {
                return $collection;
            }
        }

        throw new \InvalidArgumentException("Collection with key '$key' was not found.");
    }

    /**
     * @param Collection[]|ArrayCollection $collections
     *
     * @return ContentType
     */
    public function setCollections($collections)
    {
        $this->collections->clear();

        $includes_all = false;

        foreach ($collections as $collection) {
            if ($collection->getIdentifier() == 'all') {
                $includes_all = true;
            }
        }

        if (!$includes_all) {
            $this->addDefaultCollection();
        }

        foreach ($collections as $collection) {
            $this->addCollection($collection);
        }

        return $this;
    }

    /**
     * @param Collection $collection
     *
     * @return ContentType
     */
    public function addCollection(Collection $collection)
    {
        if (!$this->collections->containsKey($collection->getIdentifier())) {
            $this->collections->set($collection->getIdentifier(), $collection);
            $collection->setContentType($this);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     * @param array $permissions
     *
     * @return ContentType
     */
    public function setPermissions($permissions)
    {
        $this->permissions = [];
        $this->addDefaultPermissions();

        foreach ($permissions as $attribute => $roles) {
            $this->addPermission($attribute, $roles);
        }

        return $this;
    }

    public function addPermission($attribute, array $roles)
    {
        $this->permissions[$attribute] = $roles;
    }

    /**
     * @return array
     */
    public function getLocales(): array
    {
        return $this->locales ?? [];
    }

    /**
     * @param array $locales
     *
     * @return ContentType
     */
    public function setLocales(array $locales)
    {
        $this->locales = $locales;

        return $this;
    }

    /**
     * @return int
     */
    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * @param int $weight
     * @return ContentType
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }
}

