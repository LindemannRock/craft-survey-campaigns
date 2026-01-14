<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\surveycampaigns\jobs\ProcessCampaignJob;
use lindemannrock\surveycampaigns\records\CampaignRecord;
use lindemannrock\surveycampaigns\SurveyCampaigns;
use yii\web\Response;

/**
 * Campaigns Controller
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class CampaignsController extends Controller
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
     * Run all campaigns or a specific campaign (queued)
     */
    public function actionRunAll(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $campaignId = Craft::$app->getRequest()->getBodyParam('campaignId');
        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');

        // Get all sites if no specific site is provided
        $sites = $siteId
            ? [Craft::$app->getSites()->getSiteById((int) $siteId)]
            : Craft::$app->getSites()->getAllSites();

        $jobsQueued = 0;

        foreach ($sites as $site) {
            if (!$site) {
                continue;
            }

            // Get campaigns to run
            $campaigns = $this->getCampaignsToRun($campaignId, $site->id);

            foreach ($campaigns as $campaignRecord) {
                // Queue a job for each campaign/site combination
                Craft::$app->getQueue()->push(new ProcessCampaignJob([
                    'campaignId' => $campaignRecord->id,
                    'siteId' => $site->id,
                    'sendSms' => true,
                    'sendEmail' => true,
                ]));
                $jobsQueued++;

                $this->logInfo('Campaign job queued', [
                    'campaignId' => $campaignRecord->id,
                    'siteId' => $site->id,
                ]);
            }
        }

        $this->logInfo('Campaign jobs queued', ['count' => $jobsQueued]);

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'jobsQueued' => $jobsQueued,
            ]);
        }

        if ($jobsQueued > 0) {
            Craft::$app->getSession()->setNotice(
                Craft::t('formie-campaigns', '{count} campaign job(s) queued. Check the queue for progress.', ['count' => $jobsQueued])
            );
        } else {
            Craft::$app->getSession()->setNotice(
                Craft::t('formie-campaigns', 'No campaigns to process.')
            );
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Get campaigns to run
     *
     * @return CampaignRecord[]
     */
    private function getCampaignsToRun(?string $campaignId, int $siteId): array
    {
        if ($campaignId) {
            $record = CampaignRecord::findOneForSite((int)$campaignId, $siteId);
            return $record ? [$record] : [];
        }

        // Get all campaign entries from the configured section
        $settings = SurveyCampaigns::$plugin->getSettings();
        if (empty($settings->campaignSectionHandle)) {
            return [];
        }

        $entries = Entry::find()
            ->section($settings->campaignSectionHandle)
            ->siteId($siteId)
            ->all();

        $campaigns = [];
        foreach ($entries as $entry) {
            $record = CampaignRecord::findOneForSite($entry->id, $siteId);
            if ($record) {
                $campaigns[] = $record;
            }
        }

        return $campaigns;
    }
}
