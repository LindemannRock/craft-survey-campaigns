<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\services;

use craft\base\Component;
use craft\elements\db\ElementQueryInterface;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\surveycampaigns\SurveyCampaigns;

/**
 * Campaigns Service
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class CampaignsService extends Component
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('formie-campaigns');
    }

    /**
     * Get a campaign element query
     */
    public function find(): ElementQueryInterface
    {
        $settings = SurveyCampaigns::$plugin->getSettings();
        $elementType = $settings->campaignElementType;

        $query = $elementType::find();

        // Filter by section if configured
        if (!empty($settings->campaignSectionHandle)) {
            $query->section($settings->campaignSectionHandle);
        }

        return $query;
    }
}
