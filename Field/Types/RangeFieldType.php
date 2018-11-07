<?php

namespace UniteCMS\CoreBundle\Field\Types;

use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UniteCMS\CoreBundle\Entity\FieldableField;
use UniteCMS\CoreBundle\Field\FieldableFieldSettings;
use UniteCMS\CoreBundle\Field\FieldType;

class RangeFieldType extends FieldType
{
    const TYPE = "range";
    const FORM_TYPE = RangeType::class;

    /**
     * All settings of this field type by key with optional default value.
     */
    const SETTINGS = ['min', 'max', 'step', 'required', 'initial_data', 'description'];

    function getFormOptions(FieldableField $field): array
    {
        return array_merge(
            parent::getFormOptions($field),
            [
                'attr' => [
                    'min' => $field->getSettings()->min ?? 0,
                    'max' => $field->getSettings()->max ?? 100,
                    'step' => $field->getSettings()->step ?? 1,
                ],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    function validateSettings(FieldableFieldSettings $settings, ExecutionContextInterface $context)
    {
        // Validate allowed and required settings.
        parent::validateSettings($settings, $context);

        // Only continue, if there are no violations yet.
        if ($context->getViolations()->count() > 0) {
            return;
        }

        // validate if initial data is a integer
        if (isset($settings->initial_data) && !is_int($settings->initial_data)) {
            $context->buildViolation('invalid_initial_data')->atPath('initial_data')->addViolation();
        }
    }
}
