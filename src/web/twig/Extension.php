<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\web\twig;

use lindemannrock\surveycampaigns\SurveyCampaigns;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig Extension
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class Extension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @inheritdoc
     */
    public function getGlobals(): array
    {
        return [
            'formieCampaigns' => SurveyCampaigns::$plugin,
        ];
    }
}
