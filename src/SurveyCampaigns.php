<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * Campaign management for automotive surveys with SMS and email invitations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\elements\db\ElementQuery;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\web\Application as WebApplication;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\LoggingLibrary;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\surveycampaigns\behaviors\CampaignBehavior;
use lindemannrock\surveycampaigns\behaviors\CampaignQueryBehavior;
use lindemannrock\surveycampaigns\behaviors\FormBehavior;
use lindemannrock\surveycampaigns\fields\CampaignSettingsField;
use lindemannrock\surveycampaigns\models\Settings;
use lindemannrock\surveycampaigns\services\CampaignsService;
use lindemannrock\surveycampaigns\services\CustomersService;
use lindemannrock\surveycampaigns\services\EmailsService;
use lindemannrock\surveycampaigns\services\SmsService;
use lindemannrock\surveycampaigns\variables\SurveyCampaignsVariable;
use lindemannrock\surveycampaigns\web\twig\Extension;
use verbb\formie\elements\Form;
use verbb\formie\events\SubmissionEvent;
use verbb\formie\services\Submissions;
use yii\base\Event;

/**
 * Survey Campaigns Plugin
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 *
 * @property-read CampaignsService $campaigns
 * @property-read CustomersService $customers
 * @property-read EmailsService $emails
 * @property-read SmsService $sms
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class SurveyCampaigns extends Plugin
{
    use LoggingTrait;

    /**
     * @var SurveyCampaigns|null Singleton plugin instance
     */
    public static ?SurveyCampaigns $plugin = null;

    /**
     * @var string Plugin schema version for migrations
     */
    public string $schemaVersion = '4.0.3';

    /**
     * @var bool Whether the plugin exposes a control panel settings page
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool Whether the plugin registers a control panel section
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Set alias for resources
        Craft::setAlias('@lindemannrock/surveycampaigns', __DIR__);

        // Bootstrap base module (logging + Twig extension)
        PluginHelper::bootstrap(
            $this,
            'surveyCampaignsHelper',
            ['surveyCampaigns:viewLogs'],
            ['surveyCampaigns:downloadLogs']
        );
        PluginHelper::applyPluginNameFromConfig($this);

        // Set up services
        $this->setComponents([
            'campaigns' => CampaignsService::class,
            'customers' => CustomersService::class,
            'emails' => EmailsService::class,
            'sms' => SmsService::class,
        ]);

        // Set controller namespace based on app type
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'lindemannrock\\surveycampaigns\\console\\controllers';
        }
        if (Craft::$app instanceof WebApplication) {
            $this->controllerNamespace = 'lindemannrock\\surveycampaigns\\controllers';
        }

        // Register translations
        Craft::$app->i18n->translations['formie-campaigns'] = [
            'class' => \craft\i18n\PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => __DIR__ . '/translations',
            'forceTranslation' => true,
            'allowOverrides' => true,
        ];

        $this->registerEventHandlers();
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $user = Craft::$app->getUser();

        // Check permissions
        $hasCampaignsAccess = $user->checkPermission('surveyCampaigns:viewCampaigns');
        $hasSettingsAccess = $user->checkPermission('surveyCampaigns:manageSettings');

        // If no access at all, hide from nav
        if (!$hasCampaignsAccess && !$hasSettingsAccess) {
            return null;
        }

        if ($item) {
            $item['label'] = $this->getSettings()->getDisplayName();
            $item['icon'] = '@appicons/share.svg';

            $item['subnav'] = [];

            // Campaigns
            if ($hasCampaignsAccess) {
                $item['subnav']['campaigns'] = [
                    'label' => Craft::t('formie-campaigns', 'Campaigns'),
                    'url' => 'formie-campaigns',
                ];
            }

            // System Logs (using logging library)
            if (Craft::$app->getPlugins()->isPluginInstalled('logging-library') &&
                Craft::$app->getPlugins()->isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'surveyCampaigns:viewLogs',
                ]);
            }

            // Settings
            if ($hasSettingsAccess) {
                $item['subnav']['settings'] = [
                    'label' => Craft::t('formie-campaigns', 'Settings'),
                    'url' => 'formie-campaigns/settings',
                ];
            }
        }

        return $item;
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->controller->redirect('formie-campaigns/settings');
    }

    /**
     * Translation helper
     */
    public static function t(string $message, array $params = [], ?string $language = null): string
    {
        return Craft::t('formie-campaigns', $message, $params, $language);
    }

    /**
     * Register all event handlers
     */
    private function registerEventHandlers(): void
    {
        // Register behaviors on elements
        Event::on(
            Element::class,
            Element::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['surveyCampaign'] = CampaignBehavior::class;
            }
        );

        Event::on(
            ElementQuery::class,
            ElementQuery::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['surveyCampaignQuery'] = CampaignQueryBehavior::class;
            }
        );

        Event::on(
            Form::class,
            Form::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['surveyCampaignForm'] = FormBehavior::class;
            }
        );

        // Register field type
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = CampaignSettingsField::class;
            }
        );

        // Handle form submissions for surveys
        Event::on(
            Submissions::class,
            Submissions::EVENT_AFTER_SUBMISSION,
            function(SubmissionEvent $event) {
                if (!$event->success) {
                    return;
                }
                $submission = $event->submission;
                $invitationCode = Craft::$app->getRequest()->get('invitationCode');
                if (empty($invitationCode)) {
                    return;
                }

                self::$plugin->customers->processCampaignSubmission($submission, $invitationCode);
            }
        );

        // Register template roots
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['survey-campaigns'] = __DIR__ . '/templates';
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['survey-campaigns'] = __DIR__ . '/templates';
            }
        );

        // Register CP URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, $this->getCpUrlRules());
            }
        );

        // Register site URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['customers/load'] = 'formie-campaigns/customers/load';
                $event->rules['customers/delete'] = 'formie-campaigns/customers/delete-from-cp';

                $settings = $this->getSettings();
                if (isset($settings->invitationTemplate)) {
                    $event->rules[$settings->invitationRoute] = ['template' => $settings->invitationTemplate];
                }
            }
        );

        // Register Twig variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('formieCampaigns', SurveyCampaignsVariable::class);
            }
        );

        // Register permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $settings = $this->getSettings();
                $event->permissions[] = [
                    'heading' => $settings->getDisplayName(),
                    'permissions' => $this->getPluginPermissions(),
                ];
            }
        );

        // Register Twig extension
        Craft::$app->getView()->registerTwigExtension(new Extension());
    }

    /**
     * Get CP URL rules
     */
    private function getCpUrlRules(): array
    {
        return [
            'formie-campaigns' => ['template' => 'formie-campaigns/campaigns/index'],
            'formie-campaigns/<campaignId:\d+>/customers' => ['template' => 'formie-campaigns/campaigns/customers'],
            'formie-campaigns/<campaignId:\d+>/add-customer' => ['template' => 'formie-campaigns/campaigns/addCustomer'],
            'formie-campaigns/<campaignId:\d+>/import-customers' => ['template' => 'formie-campaigns/campaigns/importCustomers'],
            'formie-campaigns/<campaignId:\d+>/export-customers' => 'formie-campaigns/customers/export-customers',
            'formie-campaigns/customers/load' => 'formie-campaigns/customers/load',
            'formie-campaigns/settings' => 'formie-campaigns/settings/index',
        ];
    }

    /**
     * Get plugin permissions
     */
    private function getPluginPermissions(): array
    {
        return [
            'surveyCampaigns:manageCampaigns' => [
                'label' => Craft::t('formie-campaigns', 'Manage campaigns'),
                'nested' => [
                    'surveyCampaigns:viewCampaigns' => [
                        'label' => Craft::t('formie-campaigns', 'View campaigns'),
                    ],
                    'surveyCampaigns:createCampaigns' => [
                        'label' => Craft::t('formie-campaigns', 'Create campaigns'),
                    ],
                    'surveyCampaigns:editCampaigns' => [
                        'label' => Craft::t('formie-campaigns', 'Edit campaigns'),
                    ],
                    'surveyCampaigns:deleteCampaigns' => [
                        'label' => Craft::t('formie-campaigns', 'Delete campaigns'),
                    ],
                ],
            ],
            'surveyCampaigns:manageCustomers' => [
                'label' => Craft::t('formie-campaigns', 'Manage customers'),
                'nested' => [
                    'surveyCampaigns:viewCustomers' => [
                        'label' => Craft::t('formie-campaigns', 'View customers'),
                    ],
                    'surveyCampaigns:importCustomers' => [
                        'label' => Craft::t('formie-campaigns', 'Import customers'),
                    ],
                    'surveyCampaigns:deleteCustomers' => [
                        'label' => Craft::t('formie-campaigns', 'Delete customers'),
                    ],
                ],
            ],
            'surveyCampaigns:viewLogs' => [
                'label' => Craft::t('formie-campaigns', 'View logs'),
                'nested' => [
                    'surveyCampaigns:downloadLogs' => [
                        'label' => Craft::t('formie-campaigns', 'Download logs'),
                    ],
                ],
            ],
            'surveyCampaigns:manageSettings' => [
                'label' => Craft::t('formie-campaigns', 'Manage settings'),
            ],
        ];
    }
}
