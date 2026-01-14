<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\helpers\ArrayHelper;
use lindemannrock\surveycampaigns\records\CampaignRecord;
use lindemannrock\surveycampaigns\SurveyCampaigns;

/**
 * Campaign Settings Field
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class CampaignSettingsField extends Field implements PreviewableFieldInterface
{
    /**
     * @inheritdoc
     */
    public function beforeElementSave(ElementInterface $element, bool $isNew): bool
    {
        if (!parent::beforeElementSave($element, $isNew)) {
            return false;
        }

        $isDirty = $element->isFieldDirty($this->handle);
        $hasMethod = $element->hasMethod('setCampaignRecordAttributes');
        \Craft::info('beforeElementSave - isFieldDirty: ' . ($isDirty ? 'yes' : 'no') . ', hasMethod: ' . ($hasMethod ? 'yes' : 'no') . ', handle: ' . $this->handle, 'formie-campaigns');

        if ($isDirty && $hasMethod) {
            /** @var CampaignRecord|null $value */
            $value = $element->getFieldValue($this->handle);
            if ($value !== null) {
                $attrs = $value->getCloneableAttributes();
                \Craft::info('beforeElementSave - setting attributes: ' . json_encode($attrs), 'formie-campaigns');
                /** @phpstan-ignore method.notFound (method from CampaignBehavior) */
                $element->setCampaignRecordAttributes($attrs);
            } else {
                \Craft::info('beforeElementSave - value is null', 'formie-campaigns');
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), []);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), []);
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element = null, bool $inline = false): string
    {
        $view = Craft::$app->getView();
        $emailMessage = null;
        if (!empty($value->emailInvitationMessage)) {
            $decoded = json_decode($value->emailInvitationMessage, true);
            if (is_array($decoded)) {
                $emailMessage = $decoded['form'] ?? null;
            }
        }

        return $view->renderTemplate(
            'formie-campaigns/fields/campaign-settings',
            [
                'element' => $element,
                'field' => $this,
                'handle' => $this->handle,
                'value' => $value,
                'emailMessage' => $emailMessage,
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): ?CampaignRecord
    {
        if ($value instanceof CampaignRecord) {
            return $value;
        }

        $record = null;
        if ($element !== null && method_exists($element, 'getCampaignRecord')) {
            $record = $element->getCampaignRecord();
        }
        if ($record === null && $element !== null) {
            $record = CampaignRecord::makeForElement($element);
        }
        if ($record === null) {
            return null;
        }

        if (is_array($value)) {
            $attributes = $this->normalizeArrayAttributes($value);
            $record->setAttributes($attributes, false);
        }

        return $record;
    }

    /**
     * @inheritdoc
     */
    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        return '';
    }

    /**
     * Normalize array attributes
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizeArrayAttributes(array $attributes): array
    {
        if (isset($attributes['form'])) {
            $attributes['formId'] = is_array($attributes['form']) ? ArrayHelper::firstValue($attributes['form']) : null;
            unset($attributes['form']);
        }

        array_walk(
            $attributes,
            function(&$value) {
                if (empty($value)) {
                    $value = null;
                }
            }
        );

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return SurveyCampaigns::t('Survey Campaign Settings');
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function supportedTranslationMethods(): array
    {
        return [
            self::TRANSLATION_METHOD_NONE,
            self::TRANSLATION_METHOD_SITE,
            self::TRANSLATION_METHOD_SITE_GROUP,
            self::TRANSLATION_METHOD_LANGUAGE,
            self::TRANSLATION_METHOD_CUSTOM,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function phpType(): string
    {
        return CampaignRecord::class;
    }
}
