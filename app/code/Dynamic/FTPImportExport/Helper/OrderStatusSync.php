<?php

namespace Dynamic\FTPImportExport\Helper;

use Magento\Framework\App\ObjectManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class OrderStatusSync
{
    protected $_pageFactory;

    protected $_postFactory;

    protected $_orderCollectionFactory;

    protected $_objectManager;

    protected $_varFactory;

    protected $_invoiceService;

    protected $_transaction;

    protected $transportBuilder;

    protected $storeManager;

    protected $inlineTranslation;

    protected $scopeConfig;

    protected $helperData;

    protected $ftp;

    protected $logger;

    protected $orderRepository;
    protected $invoiceService;
    protected $transaction;
    protected $invoiceSender;
    protected $messageManager;
    protected $_searchCriteriaBuilder;
    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    protected $_creditmemoRepositoryInterface;

    protected $orderSender;
    protected $orderFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    const FTP_HOST = '45.80.134.144';
    const FTP_USER = 'KorisnikFTP';
    const FTP_PASS = 'Koliko321';
    const FTP_PORT = 21;

    public function __construct(
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Variable\Model\VariableFactory $varFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Dynamic\FTPImportExport\Helper\Data $helperData,
        \Magento\Framework\Filesystem\Io\Ftp $ftp,
        \Psr\Log\LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        InvoiceSender $invoiceSender,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Api\CreditmemoRepositoryInterface $creditmemoRepositoryInterface,
        InvoiceRepositoryInterface $invoiceRepository,
        ShipmentRepositoryInterface $shipmentRepository,
        OrderSender $orderSender,
        \Magento\Framework\Registry $registry
    ) {
        $this->_objectManager = ObjectManager::getInstance();
        $this->_varFactory = $varFactory;
        $this->_pageFactory = $pageFactory;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->helperData = $helperData;
        $this->ftp = $ftp;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->invoiceSender = $invoiceSender;
        $this->messageManager = $messageManager;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_creditmemoRepositoryInterface = $creditmemoRepositoryInterface;
        $this->invoiceRepository = $invoiceRepository;
        $this->shipmentRepository = $shipmentRepository;
        $this->orderSender = $orderSender;
        $this->registry = $registry;
    }

    public function getOrderSyncOnFTPimport()
    {
        // Check FTP connection before processing the file
        if (!$this->checkFTPConnection()) {
            $this->logger->info('FTP Connection failed. Aborting order synchronization.');
            return;
        }

        $fileData = $this->getFTPFileData();

        if ($fileData) {
            foreach ($fileData['lines'] as $key => $value) {
                // $this->logger->info('Conn parameter: ' . print_r($fileData['conn'], true));
                $this->logger->info('Key parameter: ' . print_r($key, true));
                $this->logger->info('Value parameter: ' . print_r($value, true));
                $decodedData = $this->decodeFile($value, $fileData['conn']);
                // print_r($decodedData);
                // exit;
                if ($decodedData) {
                    $this->getOrderSync($key, $decodedData, $fileData['conn'], $fileData['adminemail']);
                }
            }
        }
    }

    private function checkFTPConnection()
    {
        $host = self::FTP_HOST;
        $user = self::FTP_USER;
        $pwd = self::FTP_PASS;
        $port = self::FTP_PORT;

        try {
            $open = $this->ftp->open(array('port' => $port, 'host' => $host, 'user' => $user, 'password' => $pwd, 'ssl' => false, 'passive' => true));

            if ($open) {
                $this->logger->info('Check FTP Connection: Successful.');
                $this->ftp->close();
                return true;
            } else {
                $this->logger->info('Check FTP Connection: Failed.');
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error while connecting to FTP: ' . $e->getMessage());
            return false;
        }
    }

    private function decodeFile($value, $conn)
    {

        try {
            $this->logger->info('File Content: ' . $value);

            $decodedData = json_decode(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $value), true);
            // echo "<pre>";
            // print_r($value);
            // exit;
            $this->logger->info('Decoded Data: ' . print_r($decodedData, true));

            return $decodedData;
        } catch (\Exception $e) {
            $this->logger->error('Error while reading and decoding file: ' . $e->getMessage());
            return null;
        }
    }

    // public function getOrderSync($filename, $content, $conn, $adminemail)
    // {
    //     $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/getOrderSync.log');
    //     $logger = new \Zend\Log\Logger();
    //     $logger->addWriter($writer);

    //     $adminreceiver = 'default@default.com';
    //     $adminlogdata = 0;
    //     if (isset($adminemail)) {
    //         $adminreceiver = $adminemail;
    //     }

    //     if (!$content) {
    //         $logger->info("content empty");
    //         return;
    //     }

    //     // echo "<pre>";
    //     // print_r($content);
    //     // exit;

    //     $logger->info('Import started at ' . Date("Y-m-d H:i:s"));

    //     // $product_Data = json_decode($content);
    //     //$order_Data = json_decode(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $content), true);
    //     $order_Data = $content;
    //     if (empty($order_Data)) {
    //         return;
    //     }

    //     $logger->info("Cron wokring...");
    //     $counter = 0;
    //     $myDate = date("Y-m-d 00:00:00", strtotime(date("Y-m-d 00:00:00", strtotime(date("Y-m-d 00:00:00"))) . "-10 month"));
    //     foreach ($order_Data as $k => $ftp_order) {
    //         if ($ftp_order) {
    //             // echo "<pre>";
    //             // print_r($ftp_order);
    //             // exit;
    //             $logger->info("OrderId: " . $ftp_order['OrderId']);
    //             try {
    //                 $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    //                 $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($ftp_order['OrderId']);
    //                 $order->setErpOrderStatus($ftp_order['OrderStatus']);
    //                 $order->setErpReferenceNumber($ftp_order['OrderReferenceNumber']);
    //                 $order->setErpTrackingLink($ftp_order['PFRBarCode']);
    //                 $order->save();
    //                 $logger->info("Saved Order in DB. Order Increment ID: " . $order->getIncrementId());
    //             } catch (\Exception $e) {
    //                 $this->logger->critical('Error message', ['exception' => $e]);
    //             }

    //             $logger->info("OrderStatus: " . $ftp_order['OrderStatus']);

    //             if ($ftp_order['OrderStatus'] == "1") {
    //                 $logger->info("Order status 1 execute. We received the order.");
    //                 try {
    //                     $this->sendOrder($order, $ftp_order);
    //                 } catch (\Exception $e) {
    //                     $logger->info(++$counter . " " . $e->getMessage() . "<br><br>");
    //                 }

    //             }
    //             if ($ftp_order['OrderStatus'] == "2") {
    //                 $logger->info("Order status 2 execute. We received the order.");
    //                 try {
    //                     //$this->sendOrder($order, $ftp_order);
    //                 } catch (\Exception $e) {
    //                     $logger->info(++$counter . " " . $e->getMessage() . "<br><br>");
    //                 }

    //             }elseif ($ftp_order['OrderStatus'] == "Z") { // Zavrseno

    //                 $logger->info("Order status z execute. Order is completed.");
    //                 try {
    //                     $invoice = $this->sendInvoice($order, $ftp_order);
    //                 } catch (\Exception $e) {
    //                     $logger->info(++$counter . " " . $e->getMessage() . "<br><br>");
    //                 }

    //                 if (!empty($invoice)) {
    //                     try {
    //                         $this->createShipment($order, $ftp_order);
    //                     } catch (\Exception $e) {
    //                         $logger->info(++$counter . " " . $e->getMessage() . "<br><br>");
    //                     }
    //                 }

    //             } elseif ($ftp_order['OrderStatus'] == "S") { //Storno

    //                 $logger->info("Order status S execute. Order is cancled");
    //                 try {
    //                     $this->createCreditmemo($order, $ftp_order);
    //                 } catch (\Exception $e) {
    //                     $logger->info(++$counter . " " . $e->getMessage() . "<br><br>");
    //                 }
    //             }
    //         }
    //         ++$counter;
    //     }
    //     $logger->info(++$counter . ": Order status has been changed...<br><br>");
    //     $MoveFileToHistory = $this->MoveReadFile($filename, $conn, $adminreceiver, $adminlogdata);
    //     // exit;echo ++$counter.": Order status has been changed...<br><br>";
    // }

    public function getOrderSync($filename, $content, $conn, $adminemail)
    {
        $logger = $this->initializeLogger();

        $adminreceiver = $adminemail ?? 'default@default.com';

        $orderData = $content;
        if (!$orderData) {
            $logger->info("Content is empty");
            return;
        }

        $logger->info('Import started at ' . Date("Y-m-d H:i:s"));
        $logger->info("Cron working...");
        $counter = 0;

        foreach ($orderData as $ftpOrder) {
            if ($ftpOrder) {
                $logger->info("OrderId: " . $ftpOrder['OrderId']);
                $order = $this->loadOrderById($ftpOrder['OrderId']);

                if ($order) {
                    $this->updateOrderInformation($order, $ftpOrder);

                    if ($ftpOrder['OrderStatus'] == "1") {
                        $logger->info("Order status 1 execute. We received the order.");
                        $this->sendOrder($order, $ftpOrder);
                    } elseif ($ftpOrder['OrderStatus'] == "2") {
                        $logger->info("Order status 2 execute. We received the order.");
                        // Handle order status 2
                    } elseif ($ftpOrder['OrderStatus'] == "Z") {
                        $logger->info("Order status Z execute. Order is completed.");
                        $invoice = $this->sendInvoice($order, $ftpOrder);
                        if ($invoice) {
                            $this->createShipment($order, $ftpOrder);
                        }
                    } elseif ($ftpOrder['OrderStatus'] == "S") {
                        $logger->info("Order status S execute. Order is canceled");
                        $this->createCreditmemo($order, $ftpOrder);
                    }
                }
                ++$counter;
            }
        }

        $this->MoveReadFile($filename, $conn);
        $logger->info(++$counter . ": Order status has been changed...<br><br>");
    }

    private function initializeLogger()
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/getOrderSync.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        return $logger;
    }

    private function loadOrderById($orderId)
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            return $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId);
        } catch (\Exception $e) {
            $this->logger->critical('Error message', ['exception' => $e]);
            return null;
        }
    }

    private function updateOrderInformation($order, $ftpOrder)
    {
        $order->setErpOrderStatus($ftpOrder['OrderStatus']);
        $order->setErpReferenceNumber($ftpOrder['OrderReferenceNumber']);
        $order->setErpTrackingLink($ftpOrder['PFRBarCode']);
        $order->save();
    }

    public function sendOrder($order, $ftp_order)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orderFromDb = $this->orderRepository->get($order->getId()); // this is entity id
        // $order->setCustomerEmail($email);
        if ($orderFromDb) {
            if (!$orderFromDb->getEmailSent()) {
                try {
                    //$this->orderSender->send($orderFromDb);
                    $this->registry->register('is_email_send', 1);
                    $objectManager->create('\Magento\Sales\Model\OrderNotifier')->notify($orderFromDb);
                    $this->registry->unregister('is_email_send');
                    // $order->setEmailSent(0);
                    //$order->save();
                    // $this->logger->info('You sent the order email.');
                    $this->messageManager->addSuccess(__('You sent the order email.'));
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->messageManager->addError($e->getMessage());
                    $this->logger->critical('Error message' . $e->getMessage());
                } catch (\Exception $e) {
                    $this->logger->critical('Error message', ['exception' => $e]);
                    $this->messageManager->addError(__('We can\'t send the email order right now.'));
                    $objectManager->create('Magento\Sales\Model\OrderNotifier')->critical($e);
                }
            }
        }
    }

    public function sendInvoice($order, $ftp_order)
    {
        $invoiceRecords = array();
        $searchCriteria = $this->_searchCriteriaBuilder->addFilter('order_id', $order->getId())->create();
        try {
            $invoices = $this->invoiceRepository->getList($searchCriteria);
            $invoiceRecords = $invoices->getItems();
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());
            $invoiceRecords = null;
        }
        if (!$invoiceRecords) {
            $order = $this->orderRepository->get($order->getId());
            if (!$order->canInvoice()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('The order does not allow an invoice to be created.')
                );
            }

            if ($order->canInvoice()) {

                $invoice = $this->_invoiceService->prepareInvoice($order);
                if (!$invoice) {
                    throw new \Magento\Framework\Exception\LocalizedException(__('We can\'t save the invoice right now.'));
                }
                if (!$invoice->getTotalQty()) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('You can\'t create an invoice without products.')
                    );
                }

                // $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->getOrder()->setCustomerNoteNotify(true);
                $invoice->getOrder()->setIsInProcess(true);
                // $invoice->capture();

                $transactionSave =
                    $this->_transaction
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
                $transactionSave->save();

                // send invoice emails, If you want to stop mail disable below try/catch code
                try {
                    $this->invoiceSender->send($invoice);
                } catch (\Exception $e) {
                    $this->logger->critical('Error message', ['exception' => $e]);
                    $this->messageManager->addError(__('We can\'t send the invoice email right now.'));
                }

                $invoice = $invoice->save();
                // $this->invoiceSender->send($invoice);
                $order->setState($order::STATE_PROCESSING)->save();
                $order->setStatus($order::STATE_PROCESSING)->save();
                $order->addCommentToStatusHistory(
                    __('Notified customer about invoice creation #%1.', $invoice->getId())
                )->setIsCustomerNotified(true)->save();
                $this->orderRepository->save($order);
                return $invoice;
            }
        }
        return "";
    }

    public function createShipment($order, $ftp_order)
    {
        $shipmentRecords = array();
        $searchCriteria = $this->_searchCriteriaBuilder
            ->addFilter('order_id', $order->getId())->create();
        try {
            $shipments = $this->shipmentRepository->getList($searchCriteria);
            $shipmentRecords = $shipments->getItems();
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());
            $shipmentRecords = null;
        }
        if (!$shipmentRecords) {
            $order = $this->orderRepository->get($order->getId());

            // Check if order can be shipped or has already shipped
            if (!$order->canShip()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('You can\'t create an shipment.')
                );
            }

            // Initialize the order shipment object
            $convertOrder = $this->_objectManager->create('Magento\Sales\Model\Convert\Order');
            $shipment = $convertOrder->toShipment($order);

            // Loop through order items
            foreach ($order->getAllItems() as $orderItem) {
                // Check if order item has qty to ship or is virtual
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }

                $qtyShipped = $orderItem->getQtyToShip();

                // Create shipment item with qty
                $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                // Add shipment item to shipment
                $shipment->addItem($shipmentItem);
            }

            // Register shipment
            $shipment->register();

            $shipment->getOrder()->setIsInProcess(true);

            try {
                // Save created shipment and order
                // $shipment->save();
                $shipment->getOrder()->save();

                // Send email
                $this->_objectManager->create('Magento\Shipping\Model\ShipmentNotifier')
                    ->notify($shipment);

                $shipment->save();
            } catch (\Exception $e) {
                $this->logger->critical('Error message', ['exception' => $e]);
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($e->getMessage())
                );
            }
        }
    }
    public function createCreditmemo($order, $ftp_order)
    {
        $creditmemoData = array();
        $searchCriteria = $this->_searchCriteriaBuilder->addFilter('order_id', $order->getId())->create();
        try {
            $creditmemos = $this->_creditmemoRepositoryInterface->getList($searchCriteria);
            $creditmemoData = $creditmemos->getItems();
            $this->logger->info("Creditmemo data: " . $creditmemoData);
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());
            $creditmemoData = null;
        }

        if (!$creditmemoData) {
            try {
                $order = $this->orderRepository->get($order->getId());
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $incrementId = $order->getIncrementId(); //Pass Order Increment Id
                $order = $objectManager->create('Magento\Sales\Model\Order')
                    ->loadByAttribute('increment_id', $incrementId);
                $invoice = $objectManager->create('Magento\Sales\Model\Order\Invoice');
                $creditMemoFacory = $objectManager->create('Magento\Sales\Model\Order\CreditmemoFactory');
                $creditmemoService = $objectManager->create('Magento\Sales\Model\Service\CreditmemoService');
                $creditmemoManagement = $objectManager->create(
                    \Magento\Sales\Api\CreditmemoManagementInterface::class
                );
                $creditmemoSender = $objectManager->create('\Magento\Sales\Model\Order\Email\Sender\CreditmemoSender');
                $creditMemoData['send_email'] = 1;
                $invoices = $order->getInvoiceCollection();
                $invoiceincrementid = "";
                foreach ($invoices as $invoice) {
                    $invoiceincrementid = $invoice->getIncrementId();
                }
                // echo $invoiceincrementid;
                // exit;
                if (isset($invoiceincrementid) && !empty($invoiceincrementid) && $invoiceincrementid) {
                    $invoiceobj = $invoice->loadByIncrementId($invoiceincrementid);
                    // Don't set invoice if you want to do offline refund
                    //$creditmemo->setInvoice($invoiceobj);
                }

                $creditmemo = $creditMemoFacory->createByOrder($order);

                $creditmemo->getOrder()->setCustomerNoteNotify(!empty($creditMemoData['send_email']));

                if (!empty($creditMemoData['send_email'])) {
                    $creditmemoSender->send($creditmemo);
                }
                $creditmemoService->refund($creditmemo);
            } catch (\Exception $e) {
                $this->logger->critical('Error message', ['exception' => $e]);
            }
        }
    }

    public function sendEmail($order, $status)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $transportBuilder = $objectManager->create('\Magento\Framework\Mail\Template\TransportBuilder');
        $inlineTranslation = $objectManager->create('\Magento\Framework\Translate\Inline\StateInterface');
        $storeManager = $objectManager->create('\Magento\Store\Model\StoreManagerInterface');

        if ($status == 'facture') {
            $tempId = 11;
        }
        if ($status == 'envoye') {
            $tempId = 12;
        }
        if ($status == 'en_preparation') {
            $tempId = 13;
        }
        if ($status == 'pending') {
            $tempId = 14;
        }
        // this is an example and you can change template id,fromEmail,toEmail,etc as per your need.
        $templateId = 1; // template id
        $fromEmail = "owner@domain.com";
        $fromName = "Admin";

        $toEmail = $order->getCustomerEmail(); // receiver email id
        //$toEmail = "alexandar.gordic@gmail.com";
        try {
            // template variables pass here
            $templateVars = [
                'orderIncrementId' => $order->getIncrementId(),
                'status' => $status,
                'orderId' => $order->getId(),
                'transportationChoose' => ($order->getShippingMethod() != "freeshipping_freeshipping") ? true : false
            ];

            $storeId = $storeManager->getStore()->getId();

            $from = ['email' => $fromEmail, 'name' => $fromName];
            $inlineTranslation->suspend();

            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $templateOptions = [
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $storeId
            ];
            $transport = $transportBuilder->setTemplateIdentifier($tempId, $storeScope)
                ->setTemplateOptions($templateOptions)
                ->setTemplateVars($templateVars)
                ->setFrom($from)
                ->addTo($toEmail)
                ->getTransport();
            $transport->sendMessage();
            $inlineTranslation->resume();
        } catch (\Exception $e) {
            $this->logger->critical('Error message', ['exception' => $e]);
            //echo $e->getMessage();
        }
    }

    private function getFTPFileData()
    {
        $path = '/DbToWeb';
        $host = self::FTP_HOST;
        $user = self::FTP_USER;
        $pwd = self::FTP_PASS;
        $port = self::FTP_PORT;
        $filename = "";
        if (0) {
            $filename = $this->helperData->getGeneralStock('stock_txtfileStock');
        }

        $reportReceiver = $this->helperData->getGeneralStock('display_reportreceiverStock');

        // echo $port;
        // exit;

        try {
            $open = $this->ftp->open(array('port' => $port, 'host' => $host, 'user' => $user, 'password' => $pwd, 'ssl' => false, 'passive' => true));

            if ($open) {
                $this->logger->info('FTP Connection Successful.');
                $content = [];
                $HistoryFound = 1;
                if ($filename != "") {
                    $path = $this->ftp->cd("/$path/");

                    $list = $this->ftp->ls();
                    foreach ($list as $k1 => $v1) {

                        if ($v1['text'] == $filename) {
                            $content[$v1['text']] = $this->ftp->read($v1['text']);
                        }
                    }
                } else {
                    $path = $this->ftp->cd("/$path/");
                    $list = $this->ftp->ls();

                    $filename = [];
                    foreach ($list as $k1 => $v1) {
                        if ($v1['text'] != "History") {
                            /*Remove corrupted data from the file.*/
                            if (strpos($v1['text'], 'OrderStatus') !== false) {
                                $datadd = trim($this->ftp->read($filename[] = $v1['text']));
                                $datadd = utf8_decode($datadd);
                                $datadd = str_replace('??', '', $datadd);
                                $datadd = stripslashes(html_entity_decode($datadd));

                                // $product_Data = json_decode($content);

                                // print_r($product_Data);

                                $content[$v1['text']] = $datadd;
                                // $content[$v1['text']] = $this->ftp->read($filename[] = $v1['text']);

                            }
                        }
                        if ($v1['text'] == "History") {
                            $HistoryFound = 0;
                        }
                    }
                }
                if ($HistoryFound) {
                    $this->ftp->mkdir("History");
                }

                if (empty($content)) {
                    return array('lines' => array(), 'filename' => '', 'conn' => $this->ftp);
                }

                $this->ftp->close();
                // echo "<pre>";
                // print_r($content);
                // print_r($filename);
                // print_r($content);
                // die;
                return array('lines' => $content, 'filename' => $filename, 'conn' => $this->ftp, 'adminemail' => $reportReceiver);
            }
        } catch (\Exception $e) {
            //echo $e->getMessage();
            $this->logger->critical('Error message', ['exception' => $e]);
            $this->ftp->close();
            return false;
        }
    }

    private function MoveReadFile($filename, $conn)
    {
        $path = '/DbToWeb';
        $host = self::FTP_HOST;
        $user = self::FTP_USER;
        $pwd = self::FTP_PASS;
        $port = self::FTP_PORT;

        try {
            $open = $this->ftp->open(array('port' => $port, 'host' => $host, 'user' => $user, 'password' => $pwd, 'ssl' => false, 'passive' => true));
            if ($open) {

                $conn->cd("/$path/");
                $content = $conn->mv($filename, "History/" . $filename);
                //$this->sendEmail($adminreceiver, "facture");
                $this->ftp->close();
            }
        } catch (\Exception $e) {
            $this->logger->error('Error while moving file via FTP: ' . $e->getMessage());
            if ($this->ftp && $this->ftp->isConnected()) {
                $this->ftp->close();
            }
            return false;
        }
    }
}