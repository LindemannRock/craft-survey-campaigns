<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\behaviors;

use craft\base\Element;
use craft\events\ModelEvent;
use Exception;
use lindemannrock\surveycampaigns\records\CampaignRecord;
use lindemannrock\surveycampaigns\records\CustomerRecord;
use verbb\formie\elements\Form;
use yii\base\Behavior;

/**
 * Campaign Behavior
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 *
 * @property string $campaignType
 * @property int|null $formId
 * @property string|null $senderId
 * @property string|null $emailInvitationSubject
 * @property string|null $invitationDelayPeriod
 * @property string|null $invitationExpiryPeriod
 * @property string|null $emailInvitationMessage
 * @property string|null $smsInvitationMessage
 * @property Element $owner
 */
class CampaignBehavior extends Behavior
{
    /**
     * @var CampaignRecord|null Internal copy of the Campaign record
     */
    private ?CampaignRecord $_record = null;

    /**
     * @var bool Whether we've already tried to load a CampaignRecord
     */
    private bool $_recordLoaded = false;

    /**
     * @var CustomerRecord[]|null
     */
    private ?array $_customers = null;

    private ?int $_customerCount = null;

    private ?int $_submissionCount = null;

    /**
     * @var array<string, mixed>|null Pending attributes to save
     */
    private ?array $_pendingAttributes = null;

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            ...parent::events(),
            Element::EVENT_AFTER_SAVE => fn(ModelEvent $event) => $this->handleAfterSave($event),
        ];
    }

    /**
     * Get the Campaign record
     */
    public function getCampaignRecord(): ?CampaignRecord
    {
        if (!$this->_recordLoaded) {
            $this->loadCampaignRecord();
        }

        return $this->_record;
    }

    /**
     * Load the Campaign record
     */
    public function loadCampaignRecord(?CampaignRecord $record = null): void
    {
        $this->_record = $record ?: CampaignRecord::findOneForSite($this->owner->id, $this->owner->siteId);
        $this->_recordLoaded = true;
    }

    /**
     * Save the Campaign record
     *
     * @param array<string, mixed>|null $attributes
     * @throws Exception
     */
    public function saveCampaignRecord(?array $attributes = null): void
    {
        \Craft::info('saveCampaignRecord - called with attributes: ' . json_encode($attributes), 'formie-campaigns');

        // Use canonical element ID to avoid saving to drafts/revisions
        $canonicalId = $this->owner->getCanonicalId() ?? $this->owner->id;
        $siteId = $this->owner->siteId;

        // No record loaded and no attributes - check if this is a campaign element
        if (!isset($this->_record) && empty($attributes)) {
            // Only create records for elements in the campaigns section
            if (!$this->isCampaignElement()) {
                \Craft::info('saveCampaignRecord - skipping: not a campaign element and no attributes', 'formie-campaigns');
                return;
            }
        }

        // Always look up the record for the canonical ID, not the current element ID
        $record = CampaignRecord::findOneForSite($canonicalId, $siteId);
        $pendingAttributes = $attributes;

        // If no record exists for canonical ID, get pending attributes from current record if any
        if ($record === null) {
            $currentRecord = $this->getCampaignRecord();
            if ($currentRecord !== null) {
                $pendingAttributes = array_merge($currentRecord->getCloneableAttributes(), $attributes ?? []);
            }
            $record = new CampaignRecord();
            $record->id = $canonicalId;
            $record->siteId = $siteId;
        }

        // Record not modified and not new; nothing to save.
        if (!$record->getIsNewRecord() && empty($record->getDirtyAttributes()) && empty($pendingAttributes)) {
            \Craft::info('saveCampaignRecord - skipping: record not modified', 'formie-campaigns');
            return;
        }

        $record->setAttributes($pendingAttributes, false);

        \Craft::info('saveCampaignRecord - saving record id=' . $record->id . ', siteId=' . $record->siteId . ', isNew=' . ($record->getIsNewRecord() ? 'yes' : 'no'), 'formie-campaigns');
        if (!$record->save()) {
            \Craft::error('saveCampaignRecord - save failed: ' . json_encode($record->getErrors()), 'formie-campaigns');
            throw new Exception('Could not save the Campaign record.');
        }
        \Craft::info('saveCampaignRecord - saved successfully', 'formie-campaigns');

        $this->loadCampaignRecord($record);
    }

    /**
     * Set Campaign record attributes
     *
     * @param array<string, mixed> $attributes
     */
    public function setCampaignRecordAttributes(array $attributes): void
    {
        \Craft::info('setCampaignRecordAttributes called with: ' . json_encode($attributes), 'formie-campaigns');
        $this->_pendingAttributes = $attributes;
        $this->getOrMakeRecord()->setAttributes($attributes, false);
    }

    /**
     * Handle after save event
     */
    protected function handleAfterSave(ModelEvent $event): void
    {
        $owner = $event->sender;
        \Craft::info('handleAfterSave - pendingAttributes: ' . json_encode($this->_pendingAttributes), 'formie-campaigns');
        $owner->saveCampaignRecord($this->_pendingAttributes);
        $this->_pendingAttributes = null;
    }

    // Getters and setters

    public function getCampaignType(): ?string
    {
        return $this->getCampaignRecord()?->campaignType;
    }

    public function setCampaignType(?string $value): void
    {
        $this->getOrMakeRecord()->campaignType = $value ?: null;
    }

    /**
     * @return CustomerRecord[]
     */
    public function getCustomers(): array
    {
        if (!isset($this->_customers)) {
            $this->_customers = $this->getCampaignRecord()?->getCustomers() ?? [];
        }

        return $this->_customers;
    }

    /**
     * @return CustomerRecord[]
     */
    public function getCustomersBySiteId(int $siteId): array
    {
        if (!isset($this->_customers)) {
            $this->_customers = $this->getCampaignRecord()?->getCustomersBySiteId($siteId) ?? [];
        }

        return $this->_customers;
    }

    public function getCustomerCount(): int
    {
        if (!isset($this->_customerCount)) {
            $this->_customerCount = count($this->getCustomers());
        }

        return $this->_customerCount;
    }

    public function getCustomerCountBySiteId(int $siteId): int
    {
        if (!isset($this->_customerCount)) {
            $this->_customerCount = count($this->getCustomersBySiteId($siteId));
        }

        return $this->_customerCount;
    }

    public function getFormId(): ?int
    {
        return $this->getCampaignRecord()?->formId;
    }

    public function setFormId(?int $value): void
    {
        $this->getOrMakeRecord()->formId = $value ?: null;
        $this->getOrMakeRecord()->resetForm();
    }

    public function getInvitationDelayPeriod(): ?string
    {
        return $this->getCampaignRecord()?->invitationDelayPeriod;
    }

    public function setInvitationDelayPeriod(?string $value): void
    {
        $this->getOrMakeRecord()->invitationDelayPeriod = $value ?: null;
    }

    public function getInvitationExpiryPeriod(): ?string
    {
        return $this->getCampaignRecord()?->invitationExpiryPeriod;
    }

    public function setInvitationExpiryPeriod(?string $value): void
    {
        $this->getOrMakeRecord()->invitationExpiryPeriod = $value ?: null;
    }

    public function getSenderId(): ?string
    {
        return $this->getCampaignRecord()?->senderId;
    }

    public function getEmailInvitationSubject(): ?string
    {
        return $this->getCampaignRecord()?->emailInvitationSubject;
    }

    public function getEmailInvitationMessage(): ?string
    {
        return $this->getCampaignRecord()?->emailInvitationMessage;
    }

    public function setEmailInvitationMessage(?string $value): void
    {
        $this->getOrMakeRecord()->emailInvitationMessage = $value ?: null;
    }

    public function getSmsInvitationMessage(): ?string
    {
        return $this->getCampaignRecord()?->smsInvitationMessage;
    }

    public function setSmsInvitationMessage(?string $value): void
    {
        $this->getOrMakeRecord()->smsInvitationMessage = $value ?: null;
    }

    public function getSubmissionCount(): int
    {
        if (!isset($this->_submissionCount)) {
            $customersWithSubmissions = array_filter(
                $this->getCustomers(),
                fn(CustomerRecord $customer) => (bool)$customer->submissionId
            );
            $this->_submissionCount = count($customersWithSubmissions);
        }

        return $this->_submissionCount;
    }

    public function getForm(): ?Form
    {
        return $this->getCampaignRecord()?->getForm();
    }

    /**
     * Get or create a Campaign record
     */
    private function getOrMakeRecord(): CampaignRecord
    {
        $record = $this->getCampaignRecord();
        if (!$record) {
            $this->_record = CampaignRecord::makeForElement($this->owner);
        }

        return $this->_record;
    }

    /**
     * Check if the owner element is a campaign element (in the configured section)
     */
    private function isCampaignElement(): bool
    {
        $owner = $this->owner;

        // Must be an Entry
        if (!$owner instanceof \craft\elements\Entry) {
            return false;
        }

        // Check if in the configured campaigns section
        $settings = \lindemannrock\surveycampaigns\SurveyCampaigns::$plugin->getSettings();
        $sectionHandle = $settings->campaignSectionHandle;

        if (empty($sectionHandle)) {
            return false;
        }

        $section = $owner->getSection();
        return $section && $section->handle === $sectionHandle;
    }
}
