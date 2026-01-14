<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\web\assets;

use craft\web\AssetBundle;

/**
 * DataTable Asset Bundle
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class DataTableAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@lindemannrock/surveycampaigns/resources/';

        $this->css = [
            'css/datatables.min.css',
        ];

        $this->js = [
            'js/datatables.min.js',
        ];

        parent::init();
    }
}
