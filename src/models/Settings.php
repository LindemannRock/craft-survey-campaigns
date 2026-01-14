<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\models;

use craft\base\Model;
use craft\elements\Entry;
use lindemannrock\base\traits\SettingsDisplayNameTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Settings Model
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class Settings extends Model
{
    use LoggingTrait;
    use SettingsDisplayNameTrait;

    // =========================================================================
    // PLUGIN SETTINGS
    // =========================================================================

    /**
     * @var string The public-facing name of the plugin
     */
    public string $pluginName = 'Survey Campaigns';

    /**
     * @var array<int|string, string>|null Campaign type options for dropdown
     */
    public ?array $campaignTypeOptions = null;

    /**
     * @var string Element type to use for campaigns
     */
    public string $campaignElementType = Entry::class;

    /**
     * @var string|null Section handle to filter campaigns (e.g., 'surveys')
     */
    public ?string $campaignSectionHandle = null;

    /**
     * @var string Route for invitation links
     */
    public string $invitationRoute = 'formie-campaigns/invitation';

    /**
     * @var string|null Template to use for invitation pages
     */
    public ?string $invitationTemplate = null;

    /**
     * @var int|null Default SMS Manager sender ID to use for campaigns
     */
    public ?int $defaultSenderIdId = null;

    // =========================================================================
    // LOGGING SETTINGS
    // =========================================================================

    /**
     * @var string Log level for the logging library
     */
    public string $logLevel = 'error';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('formie-campaigns');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get campaign type options formatted for dropdowns
     */
    public function getCampaignTypeOptions(): ?array
    {
        if (!is_array($this->campaignTypeOptions)) {
            return null;
        }

        if (array_is_list($this->campaignTypeOptions)) {
            return array_combine($this->campaignTypeOptions, $this->campaignTypeOptions);
        }

        return $this->campaignTypeOptions;
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['pluginName', 'string'],
            ['pluginName', 'default', 'value' => 'Survey Campaigns'],
            ['invitationRoute', 'string'],
            ['invitationRoute', 'default', 'value' => 'formie-campaigns/invitation'],
            ['invitationTemplate', 'string'],
            ['campaignElementType', 'string'],
            ['defaultSenderIdId', 'integer'],
            [['logLevel'], 'in', 'range' => ['debug', 'info', 'warning', 'error']],
        ];
    }
}
