<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\integrations;

use Craft;
use craft\helpers\UrlHelper;
use lindemannrock\smsmanager\integrations\IntegrationInterface;
use lindemannrock\surveycampaigns\records\CampaignRecord;
use lindemannrock\surveycampaigns\SurveyCampaigns;
use verbb\formie\elements\Form;

/**
 * SMS Manager Integration
 *
 * Reports Survey Campaigns usage to SMS Manager so the delete guard in
 * `SenderIdsService::deleteSenderId()` blocks deletion of any sender this
 * plugin is using.
 *
 * Usage sources covered:
 *  - Per-campaign assignment (`CampaignRecord.senderId`)
 *  - Plugin-default sender (`defaultSenderIdId`)
 *
 * Survey Campaigns doesn't store a provider directly — providers are
 * implied by whichever sender is selected — so `getProviderUsages()`
 * returns an empty array.
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     4.1.0
 */
class SmsManagerIntegration implements IntegrationInterface
{
    /**
     * @inheritdoc
     */
    public function getProviderUsages(int $providerId): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getSenderIdUsages(int $senderIdId): array
    {
        $usages = [];

        /** @var CampaignRecord[] $campaigns */
        $campaigns = CampaignRecord::find()
            ->where(['senderId' => $senderIdId])
            ->all();

        foreach ($campaigns as $campaign) {
            $label = 'Campaign #' . $campaign->id;
            if ($campaign->formId) {
                $form = Form::find()->id($campaign->formId)->one();
                if ($form) {
                    $label = $form->title;
                }
            }

            $usages[] = [
                'label' => $label,
                'editUrl' => UrlHelper::cpUrl('survey-campaigns/campaigns/' . $campaign->id),
            ];
        }

        // Plugin-default sender — compare directly against the stored int id.
        $settings = SurveyCampaigns::$plugin->getSettings();
        if ($settings->defaultSenderIdId !== null && (int) $settings->defaultSenderIdId === $senderIdId) {
            $usages[] = [
                'label' => Craft::t('survey-campaigns', 'Plugin Default Sender ID'),
                'editUrl' => UrlHelper::cpUrl('survey-campaigns/settings'),
            ];
        }

        return $usages;
    }
}
