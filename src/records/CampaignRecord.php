<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\records;

use craft\base\ElementInterface;
use verbb\formie\elements\Form;
use verbb\formie\Formie;

/**
 * Campaign Record
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 *
 * @property int $id
 * @property int $siteId
 * @property string $campaignType
 * @property int|null $formId
 * @property string|null $invitationDelayPeriod
 * @property string|null $invitationExpiryPeriod
 * @property string|null $emailInvitationMessage
 * @property string|null $emailInvitationSubject
 * @property string|null $smsInvitationMessage
 * @property string|null $senderId
 */
class CampaignRecord extends BaseRecord
{
    public const TABLE_NAME = 'formiecampaigns_campaigns';

    private ?Form $_form = null;

    /**
     * Get all customers for this campaign
     *
     * @return CustomerRecord[]
     */
    public function getCustomers(): array
    {
        return CustomerRecord::findAll([
            'campaignId' => $this->id,
        ]);
    }

    /**
     * Get customers with pending SMS invitations
     *
     * @return array<CustomerRecord>
     */
    public function getPendingSmsCustomers(int $siteId = 1): array
    {
        /** @var array<CustomerRecord> $results */
        $results = CustomerRecord::find()
            ->where([
                'campaignId' => $this->id,
                'siteId' => $siteId,
                'smsSendDate' => null,
            ])
            ->andWhere(['not', ['sms' => null]])
            ->andWhere(['not', ['sms' => '']])
            ->all();

        return $results;
    }

    /**
     * Get customers with pending email invitations
     *
     * @return array<CustomerRecord>
     */
    public function getPendingEmailCustomers(int $siteId = 1): array
    {
        /** @var array<CustomerRecord> $results */
        $results = CustomerRecord::find()
            ->where([
                'campaignId' => $this->id,
                'siteId' => $siteId,
                'emailSendDate' => null,
            ])
            ->andWhere(['not', ['email' => null]])
            ->andWhere(['not', ['email' => '']])
            ->all();

        return $results;
    }

    /**
     * Get customers by site ID
     *
     * @return CustomerRecord[]
     */
    public function getCustomersBySiteId(int $siteId): array
    {
        return CustomerRecord::findAll([
            'campaignId' => $this->id,
            'siteId' => $siteId,
        ]);
    }

    /**
     * Get the associated Formie form
     */
    public function getForm(): ?Form
    {
        if (!isset($this->_form)) {
            $this->loadForm();
        }

        return $this->_form;
    }

    /**
     * Get cloneable attributes
     *
     * @return array<string, mixed>
     */
    public function getCloneableAttributes(): array
    {
        return $this->getAttributes(
            null,
            [
                'id',
                'siteId',
                'dateCreated',
                'dateUpdated',
                'uid',
            ],
        );
    }

    /**
     * Load the form
     */
    public function loadForm(?Form $form = null): void
    {
        $this->_form = $form;
        if (!$this->_form && $this->formId) {
            $this->_form = Formie::getInstance()->getForms()->getFormById($this->formId);
        }
    }

    /**
     * Reset the form cache
     */
    public function resetForm(): void
    {
        $this->_form = null;
    }

    /**
     * Find a campaign record for a specific site
     */
    public static function findOneForSite(?int $id, ?int $siteId): ?self
    {
        if (!$id || !$siteId) {
            return null;
        }

        /** @var self|null $result */
        $result = static::find()
            ->andWhere(['id' => $id])
            ->andWhere(['siteId' => $siteId])
            ->one();

        return $result;
    }

    /**
     * Create a campaign record for an element
     *
     * @param array<string, mixed> $attributes
     */
    public static function makeForElement(ElementInterface $element, array $attributes = []): self
    {
        return new self([
            'id' => $element->id,
            'siteId' => $element->siteId,
            ...$attributes,
        ]);
    }
}
