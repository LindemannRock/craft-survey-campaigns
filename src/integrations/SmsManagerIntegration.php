<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\integrations;

use craft\helpers\UrlHelper;
use lindemannrock\smsmanager\integrations\IntegrationInterface;
use lindemannrock\surveycampaigns\records\CampaignRecord;
use verbb\formie\elements\Form;

/**
 * SMS Manager Integration
 *
 * Reports Survey Campaigns usage to SMS Manager.
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
        // Survey Campaigns doesn't store provider ID directly,
        // it uses sender ID which is linked to a provider
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getSenderIdUsages(int $senderIdId): array
    {
        $usages = [];

        // Find all campaigns that use this sender ID
        /** @var CampaignRecord[] $campaigns */
        $campaigns = CampaignRecord::find()
            ->where(['senderId' => $senderIdId])
            ->all();

        foreach ($campaigns as $campaign) {
            // Get the form name for a better label
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

        return $usages;
    }
}
