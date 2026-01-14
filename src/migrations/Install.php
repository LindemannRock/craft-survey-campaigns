<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\migrations;

use craft\db\Migration;
use craft\records\Element;
use craft\records\Element_SiteSettings;
use craft\records\Site;
use lindemannrock\surveycampaigns\records\CampaignRecord;
use lindemannrock\surveycampaigns\records\CustomerRecord;
use verbb\formie\records\Form;
use verbb\formie\records\Submission;

/**
 * Install migration
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): void
    {
        // Skip if tables already exist (production migration from old plugin)
        if ($this->db->tableExists(CampaignRecord::tableName())) {
            return;
        }

        $this->createTable(CampaignRecord::tableName(), [
            'id' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'campaignType' => $this->string(),
            'formId' => $this->integer()->null(),
            'invitationDelayPeriod' => $this->string(),
            'invitationExpiryPeriod' => $this->string(),
            'emailInvitationMessage' => $this->text(),
            'emailInvitationSubject' => $this->text(),
            'smsInvitationMessage' => $this->text(),
            'senderId' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY (id, siteId)',
        ]);

        $this->addForeignKey(
            'fk_fc_campaigns_id_elements_id',
            CampaignRecord::tableName(),
            ['id'],
            Element::tableName(),
            ['id'],
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_fc_campaigns_siteId_sites_id',
            CampaignRecord::tableName(),
            ['siteId'],
            Site::tableName(),
            ['id'],
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_fc_campaigns_elementssites',
            CampaignRecord::tableName(),
            ['id', 'siteId'],
            Element_SiteSettings::tableName(),
            ['elementId', 'siteId'],
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_fc_campaigns_formId_formie_forms_id',
            CampaignRecord::tableName(),
            ['formId'],
            Form::tableName(),
            ['id'],
            'SET NULL'
        );

        $this->createTable(CustomerRecord::tableName(), [
            'id' => $this->primaryKey(),
            'campaignId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'name' => $this->string(),
            'email' => $this->string(),
            'emailInvitationCode' => $this->string(),
            'emailSendDate' => $this->dateTime(),
            'emailOpenDate' => $this->dateTime(),
            'sms' => $this->string(),
            'smsInvitationCode' => $this->string(),
            'smsSendDate' => $this->dateTime(),
            'smsOpenDate' => $this->dateTime(),
            'submissionId' => $this->integer()->null(),
            'invitationExpiryDate' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(
            'fk_fc_customers_campaignId_fc_elements_id',
            CustomerRecord::tableName(),
            ['campaignId'],
            Element::tableName(),
            ['id'],
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_fc_customers_siteId_sites_id',
            CustomerRecord::tableName(),
            ['siteId'],
            Site::tableName(),
            ['id'],
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_fc_customers_campaignsites',
            CustomerRecord::tableName(),
            ['campaignId', 'siteId'],
            CampaignRecord::tableName(),
            ['id', 'siteId'],
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_fc_customers_submissionId_formie_submissions_id',
            CustomerRecord::tableName(),
            ['submissionId'],
            Submission::tableName(),
            ['id'],
            'SET NULL'
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): void
    {
        $this->dropTableIfExists(CustomerRecord::tableName());
        $this->dropTableIfExists(CampaignRecord::tableName());
    }
}
