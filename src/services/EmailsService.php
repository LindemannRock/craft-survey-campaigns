<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\mail\Message;
use craft\web\View;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\surveycampaigns\helpers\TimeHelper;
use lindemannrock\surveycampaigns\records\CampaignRecord;
use lindemannrock\surveycampaigns\records\CustomerRecord;
use lindemannrock\surveycampaigns\SurveyCampaigns;
use Throwable;

/**
 * Emails Service
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class EmailsService extends Component
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
     * Send a notification email to a customer
     */
    public function sendNotificationEmail(CustomerRecord $customer, CampaignRecord $campaign): bool
    {
        try {
            $message = $this->getMessage($customer, $campaign);
            $result = $this->sendEmail($message);

            if ($result) {
                $customer->emailSendDate = TimeHelper::now();
                $customer->save(false);
            }

            return $result;
        } catch (Throwable $e) {
            $this->logError('Failed to build email message', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Build the email message
     */
    private function getMessage(CustomerRecord $customer, CampaignRecord $campaign): Message
    {
        $emailInvitationMessage = null;
        if (!empty($campaign->emailInvitationMessage)) {
            $decoded = json_decode($campaign->emailInvitationMessage, true);
            if (is_array($decoded)) {
                $emailInvitationMessage = $decoded;
            }
        }

        $emailMessage = $emailInvitationMessage['form'] ?? $campaign->emailInvitationMessage;
        $campaignElement = $customer->getCampaign();
        $surveyLink = SurveyCampaigns::$plugin->customers->getBitlyUrl(
            $campaignElement->getUrl() . '?invitationCode=' . $customer->emailInvitationCode
        );
        $variables = [
            'customer_name' => $customer->name,
            'survey_link' => $surveyLink,
            'defaultLanguage' => $customer->siteId == 1 ? 'en' : 'ar',
        ];

        $email = $customer->email;
        $view = Craft::$app->getView();

        $message = new Message();
        $senderName = App::env('SYSTEM_SENDER_NAME') ?? App::env('SYSTEM_EMAIL') ?? 'Survey';
        $message->setFrom([App::env('SYSTEM_EMAIL') => $senderName]);

        // Render subject and body with variable substitution
        $subject = $view->renderObjectTemplate($campaign->emailInvitationSubject, $customer, $variables);
        $textBody = $view->renderObjectTemplate($emailMessage, $customer, $variables);
        $variables['body'] = $textBody;

        // Render HTML template
        $template = '_emails/craft/index';
        $htmlBody = $view->renderTemplate($template, $variables, View::TEMPLATE_MODE_SITE);

        $message->setSubject($subject);
        $message->setHtmlBody($htmlBody);
        $message->setTextBody(strip_tags($textBody));
        $message->setReplyTo(App::env('SYSTEM_EMAIL_REPLY_TO'));
        $message->setTo([trim($email)]);

        return $message;
    }

    /**
     * Send an email message
     */
    private function sendEmail(Message $message): bool
    {
        $mailer = Craft::$app->getMailer();
        $result = false;

        try {
            $result = $mailer->send($message);
        } catch (Throwable $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $this->logError('Failed to send campaign email', ['error' => $e->getMessage()]);
        }

        if ($result) {
            $this->logInfo('Campaign email sent successfully');
        } else {
            $this->logError('Unable to send campaign email');
        }

        return $result;
    }
}
