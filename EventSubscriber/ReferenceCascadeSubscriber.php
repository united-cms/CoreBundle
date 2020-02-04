<?php

namespace UniteCMS\CoreBundle\EventSubscriber;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use UniteCMS\CoreBundle\Content\ContentInterface;
use UniteCMS\CoreBundle\Content\ContentResultInterface;
use UniteCMS\CoreBundle\ContentType\ContentTypeField;
use UniteCMS\CoreBundle\Domain\Domain;
use UniteCMS\CoreBundle\Domain\DomainManager;
use UniteCMS\CoreBundle\Event\ContentEvent;
use UniteCMS\CoreBundle\Event\ContentEventAfter;
use UniteCMS\CoreBundle\Event\ContentEventBefore;
use UniteCMS\CoreBundle\Field\FieldTypeManager;
use UniteCMS\CoreBundle\Field\Types\ReferenceOfType;
use UniteCMS\CoreBundle\Log\LoggerInterface;
use UniteCMS\CoreBundle\Security\User\UserInterface;

class ReferenceCascadeSubscriber implements EventSubscriberInterface
{

    /**
     * @var DomainManager $domainManager
     */
    protected $domainManager;

    /**
     * @var FieldTypeManager $fieldTypeManager
     */
    protected $fieldTypeManager;

    /**
     * @var EventDispatcherInterface $eventDispatcher
     */
    protected $eventDispatcher;

    public function __construct(DomainManager $domainManager, FieldTypeManager $fieldTypeManager, EventDispatcherInterface $eventDispatcher)
    {
        $this->domainManager = $domainManager;
        $this->fieldTypeManager = $fieldTypeManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ContentEventBefore::PERMANENT_DELETE => 'onPermanentDelete',
        ];
    }

    /**
     * @param ContentEvent $event
     */
    public function onPermanentDelete(ContentEvent $event) {

        $content = $event->getContent();
        $domain = $this->domainManager->current();
        $contentType = $domain->getContentTypeManager()->getAnyType($content->getType());
        $fieldType = $this->fieldTypeManager->getFieldType(ReferenceOfType::getType());

        // Find all reference_of fields.
        foreach($contentType->getFields() as $field) {
            if($field->getType() === ReferenceOfType::getType()) {

                /**
                 * @var ContentResultInterface $referencedContentResult
                 */
                $referencedContentResult = $fieldType->resolveField($content, $field, $content->getFieldData($field->getId()));

                if($referencedContentResult) {
                    foreach ($referencedContentResult->getResult() as $referencedContent) {
                        if ($field->getSettings()->get('onDelete') === 'CASCADE') {
                            $this->cascade($domain, $content, $referencedContent);
                        } else {
                            $this->setNull($domain, $content, $referencedContent, $field);
                        }
                    }
                }
            }
        }
    }

    protected function cascade(Domain $domain, ContentInterface $deletedContent, ContentInterface $referencedContent) {

        $manager = $referencedContent instanceof UserInterface ? $domain->getUserManager() : $domain->getContentManager();
        $manager->permanentDelete($domain, $referencedContent);
        $this->eventDispatcher->dispatch(new ContentEventBefore($referencedContent), ContentEventBefore::PERMANENT_DELETE);
        $manager->flush($domain);

        $domain->log(LoggerInterface::NOTICE, sprintf(
            'Cascade delete referenced "%s" content with id "%s", because "%s" content with id "%s" was hard deleted.',
            $referencedContent->getType(),
            $referencedContent->getId(),
            $deletedContent->getType(),
            $deletedContent->getId()
        ));

        $this->eventDispatcher->dispatch(new ContentEventAfter($referencedContent), ContentEventAfter::PERMANENT_DELETE);
    }

    protected function setNull(Domain $domain, ContentInterface $deletedContent, ContentInterface $referencedContent, ContentTypeField $field) {

        $manager = $referencedContent instanceof UserInterface ? $domain->getUserManager() : $domain->getContentManager();

        $data = $referencedContent->getData();
        unset($data[$field->getSettings()->get('reference_field')]);
        $manager->update($domain, $referencedContent, $data);

        $this->eventDispatcher->dispatch(new ContentEventBefore($referencedContent), ContentEventBefore::UPDATE);
        $manager->flush($domain);

        $domain->log(LoggerInterface::NOTICE, sprintf(
            'Set referenced "%s" of "%s" content with id "%s" to NULL, because "%s" content with id "%s" was hard deleted.',
            $field->getSettings()->get('reference_field'),
            $referencedContent->getType(),
            $referencedContent->getId(),
            $deletedContent->getType(),
            $deletedContent->getId()
        ));

        $this->eventDispatcher->dispatch(new ContentEventAfter($referencedContent), ContentEventAfter::UPDATE);
    }
}
