<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\behaviors;

use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\events\CancelableEvent;
use craft\helpers\Db;
use lindemannrock\surveycampaigns\records\CampaignRecord;
use yii\base\Behavior;

/**
 * Campaign Query Behavior
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 *
 * @property ElementQuery $owner
 */
class CampaignQueryBehavior extends Behavior
{
    public ?string $formieCampaignType = null;

    public ?bool $hasFormieCampaign = null;

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            ElementQuery::EVENT_AFTER_PREPARE => fn(CancelableEvent $event) => $this->handleAfterPrepare($event),
        ];
    }

    /**
     * Handle after prepare event
     */
    protected function handleAfterPrepare(CancelableEvent $event): void
    {
        /** @var ElementQuery $query */
        $query = $event->sender;

        $joinTable = CampaignRecord::tableName() . ' surveyCampaigns';
        $query->query->leftJoin($joinTable, '[[surveyCampaigns.id]] = [[subquery.elementsId]] and [[surveyCampaigns.siteId]] = [[subquery.siteSettingsId]]');
        $query->subQuery->leftJoin($joinTable, '[[surveyCampaigns.id]] = [[elements.id]] and [[surveyCampaigns.siteId]] = [[elements_sites.siteId]]');

        if ($this->hasFormieCampaign !== null) {
            $query->subQuery->andWhere([($this->hasFormieCampaign ? 'is not' : 'is'), '[[surveyCampaigns.id]]', null]);
        }

        if ($this->formieCampaignType !== null) {
            $query->subQuery->andWhere(Db::parseParam('surveyCampaigns.campaignType', $this->formieCampaignType));
        }
    }

    /**
     * Filter by campaign type
     */
    public function formieCampaignType(?string $value): ElementQueryInterface
    {
        $this->formieCampaignType = $value;
        return $this->owner;
    }

    /**
     * Filter by having a campaign
     */
    public function hasFormieCampaign(?bool $value = true): ElementQueryInterface
    {
        $this->hasFormieCampaign = $value;
        return $this->owner;
    }
}
