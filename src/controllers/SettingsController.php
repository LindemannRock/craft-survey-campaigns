<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\surveycampaigns\SurveyCampaigns;
use yii\web\Response;

/**
 * Settings Controller
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class SettingsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $this->requirePermission('surveyCampaigns:manageSettings');

        return parent::beforeAction($action);
    }

    /**
     * Settings index
     */
    public function actionIndex(): Response
    {
        $settings = SurveyCampaigns::$plugin->getSettings();

        return $this->renderTemplate('formie-campaigns/settings/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save settings
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $settings = SurveyCampaigns::$plugin->getSettings();
        $postedSettings = Craft::$app->getRequest()->getBodyParam('settings', []);

        // Fields that should be cast to int (nullable)
        $nullableIntFields = ['defaultSenderIdId'];

        // Update settings with posted values
        foreach ($postedSettings as $key => $value) {
            if (property_exists($settings, $key)) {
                if (in_array($key, $nullableIntFields, true)) {
                    $settings->$key = $value !== '' && $value !== null ? (int)$value : null;
                } else {
                    $settings->$key = $value;
                }
            }
        }

        // Validate
        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('formie-campaigns', 'Couldn\'t save settings.'));

            return $this->renderTemplate('formie-campaigns/settings/index', [
                'settings' => $settings,
            ]);
        }

        // Save plugin settings
        if (!Craft::$app->getPlugins()->savePluginSettings(SurveyCampaigns::$plugin, $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('formie-campaigns', 'Couldn\'t save settings.'));

            return $this->renderTemplate('formie-campaigns/settings/index', [
                'settings' => $settings,
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('formie-campaigns', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
