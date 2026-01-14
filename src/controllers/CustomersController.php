<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\controllers;

use Craft;
use craft\db\ActiveQuery;
use craft\web\Controller;
use craft\web\UploadedFile;
use lindemannrock\surveycampaigns\jobs\ImportCustomersJob;
use lindemannrock\surveycampaigns\records\CustomerRecord;
use lindemannrock\surveycampaigns\SurveyCampaigns;
use verbb\formie\Formie;
use yii\data\Pagination;
use yii\web\Response;

/**
 * Customers Controller
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     3.0.0
 */
class CustomersController extends Controller
{
    /**
     * Add a customer
     */
    public function actionAdd(): ?Response
    {
        $this->requirePostRequest();

        $customer = new CustomerRecord([
            'campaignId' => $this->request->getRequiredParam('campaignId'),
            'siteId' => $this->request->getRequiredParam('siteId'),
            'name' => $this->request->getRequiredParam('name'),
            'email' => $this->request->getParam('email'),
            'sms' => $this->request->getParam('sms'),
        ]);

        if (!$customer->save()) {
            return $this->returnErrorResponse(
                'Could not save Customer.',
                [
                    'customer' => $customer,
                ]
            );
        }

        return $this->returnSuccessResponse($customer);
    }

    /**
     * Load customers for DataTables
     */
    public function actionLoad(): Response
    {
        $this->requireLogin();

        $length = $this->request->getRequiredParam('length');
        $offset = $this->request->getRequiredParam('start');
        $campaignId = $this->request->getRequiredParam('campaignId');
        $draw = $this->request->getRequiredParam('draw');
        $search = $this->request->getParam('search');
        $order = $this->request->getParam('order');
        $search = $search['value'] ?? null;
        $column = $order['0']['column'] ?? null;
        $dir = $order['0']['dir'] ?? null;

        /** @var ActiveQuery $query */
        $query = CustomerRecord::find()->where([
            'campaignId' => $campaignId,
            'siteId' => (int)$this->request->getRequiredParam('siteId'),
        ]);

        if (!empty($search)) {
            $query->andFilterWhere([
                'OR',
                ['like', 'name', $search],
                ['like', 'email', $search],
                ['like', 'sms', $search],
            ]);
        }

        if (!empty($column)) {
            $orderBy = [];
            switch ($column) {
                case 1:
                    $orderBy['name'] = $dir === 'asc' ? SORT_ASC : SORT_DESC;
                    break;
                case 2:
                    $orderBy['email'] = $dir === 'asc' ? SORT_ASC : SORT_DESC;
                    break;
                case 5:
                    $orderBy['smsSendDate'] = $dir === 'asc' ? SORT_ASC : SORT_DESC;
                    $orderBy['emailSendDate'] = $dir === 'asc' ? SORT_ASC : SORT_DESC;
                    break;
                case 6:
                    $orderBy['smsOpenDate'] = $dir === 'asc' ? SORT_ASC : SORT_DESC;
                    $orderBy['emailOpenDate'] = $dir === 'asc' ? SORT_ASC : SORT_DESC;
                    break;
            }

            $query->orderBy($orderBy);
        }

        $count = $query->count();

        $pagination = new Pagination(['totalCount' => $count]);
        $pagination->setPageSize($length);

        $customers = $query->offset($offset)
            ->limit($pagination->limit)
            ->all();

        $data = [];

        /** @var CustomerRecord $customer */
        foreach ($customers as $customer) {
            $sentDate = $customer->smsSendDate ?? $customer->emailSendDate ?? '-';

            if (is_object($sentDate)) {
                $sentDate = $sentDate->format('Y-m-d h:m:s');
            }

            $openedDate = $customer->smsOpenDate ?? $customer->emailOpenDate ?? '-';

            if (is_object($openedDate)) {
                $openedDate = $openedDate->format('Y-m-d h:m:s');
            }

            $submissionLink = $customer->submissionId
                ? Formie::$plugin->getSubmissions()->getSubmissionById($customer->submissionId)?->getCpEditUrl()
                : null;
            $submissionLink = $submissionLink
                ? '<a href="' . $submissionLink . '">' . $customer->submissionId . '</a>'
                : '-';

            $data[] = [
                $customer->getSite()->getLocale()->getLanguageID(),
                $customer->name,
                $customer->email,
                $customer->sms,
                $customer->smsInvitationCode,
                $sentDate,
                $openedDate,
                $submissionLink,
                '<a id="' . $customer->id . '" class="btn delete" href="#">Delete</a>',
            ];
        }

        $response = [
            'draw' => $draw,
            'recordsTotal' => $count,
            'recordsFiltered' => $count,
            'data' => $data,
        ];

        return $this->asJson($response);
    }

    /**
     * Download sample CSV
     */
    public function actionDownloadSample(): Response
    {
        $this->requirePostRequest();
        $templatePath = Craft::$app->getPath()->getConfigPath() . DIRECTORY_SEPARATOR . 'customers-import-template.csv';

        return Craft::$app->getResponse()->sendFile($templatePath);
    }

    /**
     * Delete a customer from the CP
     */
    public function actionDeleteFromCp(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $customerId = (int)Craft::$app->request->getRequiredBodyParam('id');

        if (!SurveyCampaigns::$plugin->customers->deleteCustomerById($customerId)) {
            return $this->asJson(null);
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * Delete a customer
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $customerId = (int)Craft::$app->request->getRequiredBodyParam('id');

        if (!SurveyCampaigns::$plugin->customers->deleteCustomerById($customerId)) {
            return $this->asJson(null);
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * Import customers from CSV (queued)
     */
    public function actionImport(): ?Response
    {
        $this->requirePostRequest();

        $file = UploadedFile::getInstanceByName('file');

        if (!$this->validateCSV($file)) {
            return $this->returnErrorResponse('Invalid file type.');
        }

        if (empty($file)) {
            return $this->returnErrorResponse('No file given.');
        }

        $campaignId = (int)$this->request->getRequiredParam('campaignId');
        $queueSending = (bool)$this->request->getParam('queueSending', true);

        try {
            // Save file to temp path with unique name to avoid conflicts
            $uniqueName = uniqid('import_', true) . '_' . $file->name;
            $path = Craft::$app->getPath()->getTempAssetUploadsPath() . DIRECTORY_SEPARATOR . $uniqueName;
            $file->saveAs($path);

            // Queue the import job
            Craft::$app->getQueue()->push(new ImportCustomersJob([
                'campaignId' => $campaignId,
                'csvPath' => $path,
                'queueSending' => $queueSending,
            ]));
        } catch (\Exception $e) {
            return $this->returnErrorResponse($e->getMessage());
        }

        $successResponse = [
            'success' => true,
            'message' => 'Import job queued. Check the queue for progress.',
        ];

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson($successResponse);
        }

        Craft::$app->getSession()->setNotice(
            Craft::t('formie-campaigns', 'Import job queued. Check the queue for progress.')
        );

        return $this->redirectToPostedUrl();
    }

    /**
     * Export customers
     */
    public function actionExportCustomers(int $campaignId): Response
    {
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $siteHandle = $request->getQueryParam('site');
        $site = $siteHandle ? Craft::$app->getSites()->getSiteByHandle($siteHandle) : Craft::$app->getSites()->getPrimarySite();
        $dateRange = $request->getQueryParam('dateRange', 'all');
        $format = $request->getQueryParam('format', 'csv');

        $dates = $this->getDateRangeFromParam($dateRange);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        $query = CustomerRecord::find()
            ->where([
                'campaignId' => $campaignId,
                'siteId' => $site->id,
            ])
            ->orderBy(['dateCreated' => SORT_DESC]);

        if ($dateRange !== 'all') {
            $query->andWhere(['>=', 'dateCreated', $startDate->format('Y-m-d 00:00:00')])
                ->andWhere(['<=', 'dateCreated', $endDate->format('Y-m-d 23:59:59')]);
        }

        /** @var CustomerRecord[] $customers */
        $customers = $query->all();

        // Build filename
        $dateRangeLabel = $dateRange === 'all' ? 'alltime' : $dateRange;
        $filename = 'customers-campaign-' . $campaignId . '-' . $dateRangeLabel . '-' . date('Y-m-d-His') . '.' . $format;

        if ($format === 'csv') {
            return $this->exportCsv($customers, $filename);
        }

        // JSON export
        $data = [];
        foreach ($customers as $customer) {
            $data[] = [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'sms' => $customer->sms,
                'emailInvitationCode' => $customer->emailInvitationCode,
                'smsInvitationCode' => $customer->smsInvitationCode,
                'emailSendDate' => $this->formatDate($customer->emailSendDate),
                'smsSendDate' => $this->formatDate($customer->smsSendDate),
                'emailOpenDate' => $this->formatDate($customer->emailOpenDate),
                'smsOpenDate' => $this->formatDate($customer->smsOpenDate),
                'submissionId' => $customer->submissionId,
                'dateCreated' => $this->formatDate($customer->dateCreated),
            ];
        }

        $response = Craft::$app->getResponse();
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->content = json_encode($data, JSON_PRETTY_PRINT);

        return $response;
    }

    /**
     * Export customers as CSV
     *
     * @param CustomerRecord[] $customers
     * @param string $filename
     * @return Response
     */
    private function exportCsv(array $customers, string $filename): Response
    {
        $headers = [
            'ID',
            'Name',
            'Email',
            'SMS',
            'Email Invitation Code',
            'SMS Invitation Code',
            'Email Sent Date',
            'SMS Sent Date',
            'Email Opened Date',
            'SMS Opened Date',
            'Submission ID',
            'Date Created',
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($customers as $customer) {
            fputcsv($output, [
                $customer->id,
                $customer->name,
                $customer->email,
                $customer->sms,
                $customer->emailInvitationCode,
                $customer->smsInvitationCode,
                $this->formatDate($customer->emailSendDate),
                $this->formatDate($customer->smsSendDate),
                $this->formatDate($customer->emailOpenDate),
                $this->formatDate($customer->smsOpenDate),
                $customer->submissionId,
                $this->formatDate($customer->dateCreated),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response = Craft::$app->getResponse();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->content = $csv;

        return $response;
    }

    /**
     * Get date range from parameter
     *
     * @param string $dateRange Date range parameter
     * @return array{start: \DateTime, end: \DateTime}
     */
    private function getDateRangeFromParam(string $dateRange): array
    {
        $endDate = new \DateTime();

        $startDate = match ($dateRange) {
            'today' => new \DateTime(),
            'yesterday' => (new \DateTime())->modify('-1 day'),
            'last7days' => (new \DateTime())->modify('-7 days'),
            'last30days' => (new \DateTime())->modify('-30 days'),
            'last90days' => (new \DateTime())->modify('-90 days'),
            'all' => (new \DateTime())->modify('-365 days'),
            default => (new \DateTime())->modify('-30 days'),
        };

        if ($dateRange === 'yesterday') {
            $endDate = (new \DateTime())->modify('-1 day');
        }

        return ['start' => $startDate, 'end' => $endDate];
    }

    /**
     * Validate a CSV file
     */
    private function validateCSV(?UploadedFile $file): bool
    {
        if (!$file) {
            return false;
        }

        $csvMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/comma-separated-values',
            'application/excel',
            'application/vnd.ms-excel',
            'application/vnd.msexcel',
            'text/anytext',
            'application/octet-stream',
            'application/txt',
        ];

        if ($file->getExtension() !== 'csv') {
            return false;
        }

        if (!in_array($file->type, $csvMimeTypes)) {
            return false;
        }

        return true;
    }

    /**
     * Return an error response
     *
     * @param array<string, mixed> $routeParams
     */
    protected function returnErrorResponse(string $errorMessage, array $routeParams = []): ?Response
    {
        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['error' => $errorMessage]);
        }

        Craft::$app->getSession()->setError($errorMessage);

        Craft::$app->getUrlManager()->setRouteParams([
            'errorMessage' => $errorMessage,
        ] + $routeParams);

        return null;
    }

    /**
     * Return a success response
     */
    protected function returnSuccessResponse(mixed $returnUrlObject = null): Response
    {
        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        return $this->redirectToPostedUrl($returnUrlObject, Craft::$app->getRequest()->getReferrer());
    }

    /**
     * Format a date value for export (handles both DateTime objects and strings)
     */
    private function formatDate(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof \DateTime) {
            return $date->format('Y-m-d H:i:s');
        }

        // Already a string
        return (string) $date;
    }
}
