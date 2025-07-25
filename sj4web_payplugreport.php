<?php

if (!defined('_PS_VERSION_')) {
    exit;
}
require_once dirname(__FILE__) . '/../payplug/vendor/autoload.php';
$payplugModulePath = _PS_MODULE_DIR_ . 'payplug/classes/DependenciesClass.php';
if (file_exists($payplugModulePath)) {
    require_once $payplugModulePath;
}

class Sj4web_Payplugreport extends Module
{
    protected $module_is_active = false;
    protected $payplug_config = [];

    protected $payplug_dependencies = null;

    protected $client = null;

    public function __construct()
    {
        $this->name = 'sj4web_payplugreport';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'SJ4WEB.FR';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('SJ4WEB - Payplug Report Tracker', [], 'Modules.Sj4webpayplugreport.Admin');
        $this->description = $this->trans('Fetches Payplug fees and stores them in the database.', [], 'Modules.Sj4webpayplugreport.Admin');

        if (Module::isInstalled('payplug') && Module::isEnabled('payplug')) {
            $this->module_is_active = true;
        } else {
            $this->module_is_active = false;
        }

        if ($this->module_is_active) {
            $this->payplug_dependencies = new \Payplug\classes\DependenciesClass();
            $sandbox_mode = (bool)$this->payplug_dependencies->getPlugin()->getConfigurationClass()->getValue('sandbox_mode');
            $this->payplug_config = [
                'secretKey' => (string)$this->payplug_dependencies->getPlugin()->getConfigurationClass()->getValue(($sandbox_mode) ? 'test_api_key' : 'live_api_key'),
                'apiVersion' => $this->payplug_dependencies->getPlugin()->getApiVersion(),
            ];
            try {
                $this->client = \Payplug\Payplug::init($this->payplug_config);
            } catch (Exception $e) {
                PrestaShopLogger::addLog('Payplug Report Error: ' . $e->getMessage(), 3, null, 'Module', 0, true);
                $this->module_is_active = false;
            }
        }
        $this->ps_versions_compliancy = ['min' => '1.7.8', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionPaymentConfirmation')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('displayPaymentReturn')
            && $this->registerHook('displayAdminOrderTop')
            && $this->installSql();
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallSql();
    }

    protected function installSql()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'order_payplug_reports` (
            `id_order` INT(11) NOT NULL,
            `id_report` VARCHAR(50) NOT NULL,
            `report_treated` TINYINT(1) NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    protected function uninstallSql()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'order_payplug_reports`;';
        return Db::getInstance()->execute($sql);
    }

    /**
     * Hook to handle payment confirmation and create accounting report.
     *
     * @param array $params
     * @return void
     */
    public function hookActionPaymentConfirmation($params)
    {
        if (!$this->module_is_active) {
            return;
        }
        $id_order = (int)$params['id_order'];
        if ($id_order) {
            try {
                $order = new Order($id_order);
                if (!Validate::isLoadedObject($order)) {
                    return;
                }
                if (strtolower($order->module) !== 'payplug') {
                    return;
                }

                // On vérifie si le rapport existe déjà pour cette commande
                $row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'order_payplug_reports WHERE id_order = ' . $order->id);
                if ($row) {
                    return;
                }

                $transactionId = Db::getInstance()->getValue(
                    'SELECT transaction_id FROM ' . _DB_PREFIX_ . 'order_payment WHERE order_reference = "' . pSQL($order->reference) . '" AND transaction_id LIKE "pay_%"'
                );

                if (!$transactionId) {
                    return;
                }
                $payment = \Payplug\Payment::retrieve($transactionId);
                $createdDate = date('Y-m-d', $payment->getConsistentResource()->created_at);

                $report = \Payplug\AccountingReport::create([
                    'start_date' => $createdDate,
                    'end_date' => $createdDate
                ], $this->client);

                Db::getInstance()->insert('order_payplug_reports', [
                    'id_order' => $id_order,
                    'id_report' => pSQL($report->id),
                    'report_treated' => 0,
                ]);
            } catch (Exception $e) {
                PrestaShopLogger::addLog('Payplug Report Error: ' . $e->getMessage(), 3, null, 'Order', $id_order, true);
                return;
            }
        }
    }

    public function hookDisplayOrderConfirmation($params)
    {

        if (!$this->module_is_active) {
            return;
        }
        /** @var  $order Order */
        $order = $params['order'];
        if ($order && Validate::isLoadedObject($order)) {
            try {
                // On vérifie si la commande utilise le module Payplug
                if (strtolower($order->module) !== 'payplug') {
                    return;
                }
                // On vérifie si le rapport existe déjà pour cette commande
                $row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'order_payplug_reports WHERE id_order = ' . $order->id);
                if ($row) {
                    return;
                }

//                $transactionId = Db::getInstance()->getValue(
//                    'SELECT transaction_id FROM ' . _DB_PREFIX_ . 'order_payment WHERE order_reference = "' . pSQL($order->reference) . '" AND transaction_id LIKE "pay_%"'
//                );
//
//                if (!$transactionId) {
//                    return;
//                }
//                $payment = \Payplug\Payment::retrieve($transactionId);
//                $createdDate = date('Y-m-d', $payment->getConsistentResource()->created_at);
//
//                $report = \Payplug\AccountingReport::create([
//                    'start_date' => $createdDate,
//                    'end_date' => $createdDate
//                ], $this->client);
//
//                Db::getInstance()->insert('order_payplug_reports', [
//                    'id_order' => $order->id,
//                    'id_report' => pSQL($report->id),
//                    'report_treated' => 0,
//                ]);

                $this->createPayplugReport($order);

            } catch (Exception $e) {
                PrestaShopLogger::addLog('Payplug Report Error: ' . $e->getMessage(), 3, null, 'Order', $order->id, true);
                return;
            }
        }

    }

    protected function createPayplugReport($order)
    {
        $transactionId = Db::getInstance()->getValue(
            'SELECT transaction_id FROM ' . _DB_PREFIX_ . 'order_payment WHERE order_reference = "' . pSQL($order->reference) . '" AND transaction_id LIKE "pay_%"'
        );

        if (!$transactionId) {
            return;
        }
        $payment = \Payplug\Payment::retrieve($transactionId);
        $createdDate = date('Y-m-d', $payment->getConsistentResource()->created_at);

        $report = \Payplug\AccountingReport::create([
            'start_date' => $createdDate,
            'end_date' => $createdDate
        ], $this->client);

        Db::getInstance()->insert('order_payplug_reports', [
            'id_order' => $order->id,
            'id_report' => pSQL($report->id),
            'report_treated' => 0,
        ]);
    }


    public function hookDisplayAdminOrderTop($params)
    {
        if (!$this->module_is_active) {
            return;
        }

        $id_order = (int)$params['id_order'];
        if ($id_order) {
            try {
                $order = new Order($id_order);
                if (strtolower($order->module) !== 'payplug') {
                    return;
                }

                // On vérifie si on a déjà la commission pour cette commgande
                $row_fee = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'order_fees WHERE id_order = ' . $id_order);
                if( $row_fee && (float)$row_fee['fee'] !== 0.00) {
                    return; // Commission déjà récupérée
                }
                // On vérifie si le rapport existe et s'il a été traité
                $row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'order_payplug_reports WHERE id_order = ' . $id_order);
                if(!$row || $row['report_treated']) {
                    if($row['report_treated']) {
                        $res = Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'order_payplug_reports WHERE id_order = ' . $id_order);
                        if (!$res) {
                            PrestaShopLogger::addLog('Payplug Report Error: Unable to delete treated report for order ID ' . $id_order, 3, null, 'Order', $id_order, true);
                            return;
                        }
                    }
                    // Là faut aller chercher le rapport sinon c la merde !
                    $this->createPayplugReport($order);
                    $row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'order_payplug_reports WHERE id_order = ' . $id_order);
                }

                if (!$row || $row['report_treated']) {
                    return;
                }

                $attempts = 0;
                $maxAttempts = 5;
                $report = null;
                $id_report = $row['id_report'];

                while ($attempts < $maxAttempts) {
                    if ($attempts > 0) { // Si on a déjà essayé au moins une fois, on attend 2 secondes avant de réessayer
                        sleep(2);
                    }
                    $attempts++;
                    $report = \Payplug\AccountingReport::retrieve($id_report);
                    if (!empty($report->temporary_url)) {
                        break;
                    }
                }

                if (empty($report) || empty($report->temporary_url)) {
                    return;
                }

                // On récupère le CSV du rapport
                $csv = $this->getCsvReport($report, $id_order);
                if (!$csv) {
                    return;
                }

                // On traite le CSV récupéré
                $this->processPayplugReportCsv($csv);

                Db::getInstance()->update('order_payplug_reports', ['report_treated' => 1], 'id_report = "' . pSQL($id_report) . '"');
            } catch (Exception $e) {
                PrestaShopLogger::addLog('Payplug Report Error: ' . $e->getMessage(), 3, null, 'Order', $id_order, true);
                return;
            }

        }
    }

    public function getContent()
    {
        $html = '<h2>Suivi des rapports Payplug</h2>';
        $rows = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'order_payplug_reports ORDER BY date_add DESC LIMIT 100');
        $html .= '<table class="table"><thead><tr><th>Commande</th><th>ID Report</th><th>Traité</th><th>Date</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>#' . $row['id_order'] . '</td>';
            $html .= '<td>' . $row['id_report'] . '</td>';
            $html .= '<td>' . ($row['report_treated'] ? '✅' : '❌') . '</td>';
            $html .= '<td>' . $row['date_add'] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * @param \Payplug\Resource\AccountingReport $report
     * @param int $id_order
     * @return false|string
     */
    protected function getCsvReport(\Payplug\Resource\AccountingReport $report, int $id_order): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: PHP\r\n"
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        $csv = file_get_contents($report->temporary_url, false, $context);
        if (!$csv) {
            PrestaShopLogger::addLog('Payplug Report Info: Unable to fetch report CSV for order ID ' . $id_order, 3, null, 'Order', $id_order, true);
            return false;
        }
        return $csv;
    }

    /**
     * @param string $csv
     * @return void
     * @throws PrestaShopDatabaseException
     */
    protected function processPayplugReportCsv(string $csv): void
    {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $headers = fgetcsv($handle, 0);
        while (($data = fgetcsv($handle, 0)) !== false) {
            $rowData = array_combine($headers, $data);
            if (!isset($rowData['metadata'])) {
                continue;
            }
            $meta = json_decode($rowData['metadata'], true);
            if (!is_array($meta) || !isset($meta['Order'])) {
                continue;
            }
            $id_order_csv = (int)$meta['Order'];
            $fee_raw = isset($rowData['total_fees_excl._vat_(€)']) ? str_replace(',', '.', $rowData['total_fees_excl._vat_(€)']) : '';
            $fee = $fee_raw !== '' ? (float)$fee_raw : null;
            $exists = Db::getInstance()->getRow('SELECT fee FROM ' . _DB_PREFIX_ . 'order_fees WHERE id_order = ' . $id_order_csv . ' AND method = "payplug"');
            if (!$exists) {
                Db::getInstance()->insert('order_fees', [
                    'id_order' => $id_order_csv,
                    'method' => 'payplug',
                    'fee' => $fee
                ]);
            } elseif ($exists['fee'] === null && $fee !== null) {
                Db::getInstance()->update('order_fees', [
                    'fee' => $fee
                ], 'id_order = ' . $id_order_csv . ' AND method = "payplug"');
            }
        }
        fclose($handle);
    }

}

