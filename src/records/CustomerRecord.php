<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\records;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\DateTimeHelper;
use craft\models\Site;
use DateInterval;
use lindemannrock\surveycampaigns\helpers\TimeHelper;
use lindemannrock\surveycampaigns\SurveyCampaigns;

/**
 * Customer Record
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 *
 * @property int $id
 * @property int $campaignId
 * @property int $siteId
 * @property string|null $name
 * @property string|null $email
 * @property string|null $emailInvitationCode
 * @property \DateTime|null $emailSendDate
 * @property \DateTime|null $emailOpenDate
 * @property string|null $sms
 * @property string|null $smsInvitationCode
 * @property \DateTime|null $smsSendDate
 * @property \DateTime|null $smsOpenDate
 * @property int|null $submissionId
 * @property \DateTime|null $invitationExpiryDate
 * @property \DateTime|null $dateCreated
 * @property \DateTime|null $dateUpdated
 */
class CustomerRecord extends BaseRecord
{
    public const TABLE_NAME = 'formiecampaigns_customers';

    private ?ElementInterface $_campaign = null;

    /**
     * @var string[]
     */
    protected array $dateTimeAttributes = [
        'emailSendDate',
        'emailOpenDate',
        'smsSendDate',
        'smsOpenDate',
        'invitationExpiryDate',
    ];

    /**
     * @inheritdoc
     */
    public function beforeSave($insert): bool
    {
        $invitationCode = SurveyCampaigns::$plugin->customers->getUniqueInvitationCode();

        if (empty($this->emailInvitationCode)) {
            $this->emailInvitationCode = $invitationCode;
        }

        if (empty($this->smsInvitationCode)) {
            $this->smsInvitationCode = $invitationCode;
        }

        if ($this->getIsNewRecord() && empty($this->invitationExpiryDate)) {
            $campaign = $this->getCampaign();
            $expiryPeriod = $campaign !== null && method_exists($campaign, 'getInvitationExpiryPeriod')
                ? $campaign->getInvitationExpiryPeriod()
                : null;
            if ($expiryPeriod) {
                $expiryDate = new DateInterval($expiryPeriod);
                $this->invitationExpiryDate = TimeHelper::fromNow($expiryDate);
            }
        }

        return parent::beforeSave($insert);
    }

    /**
     * Get the campaign element
     */
    public function getCampaign(): ?ElementInterface
    {
        if (!isset($this->_campaign)) {
            $this->_campaign = Craft::$app->elements->getElementById($this->campaignId, null, $this->siteId);
        }

        return $this->_campaign;
    }

    /**
     * Get the site
     */
    public function getSite(): ?Site
    {
        return Craft::$app->sites->getSiteById($this->siteId);
    }

    /**
     * Check if this customer has a submission
     */
    public function hasSubmission(): bool
    {
        return (bool)$this->submissionId;
    }

    /**
     * Check if the invitation has expired
     */
    public function invitationIsExpired(): bool
    {
        if (!$this->invitationExpiryDate) {
            return false;
        }

        return $this->invitationExpiryDate < DateTimeHelper::now();
    }

    /**
     * Find all customers with outstanding email invitations
     *
     * @return array<static>
     */
    public static function findAllWithOutstandingEmailInvitation(): array
    {
        /** @var array<static> $results */
        $results = static::find()
            ->where(['emailSendDate' => null])
            ->all();

        return $results;
    }

    /**
     * Find all customers with outstanding SMS invitations
     *
     * @return array<static>
     */
    public static function findAllWithOutstandingSmsInvitation(): array
    {
        /** @var array<static> $results */
        $results = static::find()
            ->where(['smsSendDate' => null])
            ->all();

        return $results;
    }
}
