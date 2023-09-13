<?php
namespace Dynamic\OnlineComplaint\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Escaper;
use Dynamic\OnlineComplaint\Model\Mail\Template\AddEmailAttachemnt as TransportBuilder;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\DataObject;

class Index extends Action
{
    protected $resultFactory;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $file;

    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Filesystem $fileSystem,
        \Magento\MediaStorage\Model\File\UploaderFactory $uploaderFactory,
        \Magento\Framework\Image\AdapterFactory $adapterFactory,
        StateInterface $inlineTranslation,
        Escaper $escaper,
        File $file,
        TransportBuilder $transportBuilder
    ) {
        $this->storemanager = $storeManager;
        $this->_resource = $resource;
        $this->resultFactory = $resultFactory;
        $this->fileSystem = $fileSystem;
        $this->adapterFactory = $adapterFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->inlineTranslation = $inlineTranslation;
        $this->escaper = $escaper;
        $this->file = $file;
        $this->transportBuilder = $transportBuilder;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $data = $this->getRequest()->getPostValue();
        $connection = $this->_resource->getConnection();
        $table_name = $connection->getTableName('online_compaint');
        $insert_data = [
            'buyer_first_and_last_name' => $data['buyer_first_and_last_name'],
            'account_type' => $data['account_type'],
            'email' => $data['email'],
            'order_number' => $data['order_number'],
            'complaint_related_product' => $data['complaint_related_product'],
            'complaint_regarding' => $data['complaint_regarding'],
            'describe_problem' => $data['Describe_the_problemcomment'],
            'attachment' => $_FILES['attachment']['name']
        ];
        $data_save = $connection->insert($table_name, $insert_data);
        $this->sendEmail($this->validatedParams());
       
        $this->messageManager->addSuccess(
            __('Your Order Complaint Successfully Submited. We\'ll respond to you very soon.')
        );
        $this->_redirect('online-complaint-form');
        return;
    }

     /**
     * Method to send email.
     *
     * @param array $post Post data from contact form
     *
     * @return void
     */
    private function sendEmail($post)
    {
        $this->send(
            $post['email'],
            ['data' => new DataObject($post)]
        );
    }
    public function send($replyTo, array $variables)
    {
        $targetDirectory = 'complaint/';
        $uploader = $this->uploaderFactory->create(['fileId' => 'attachment']);
        $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
        $uploader->setAllowCreateFolders(true);
        $uploader->setFilesDispersion(false);
        $uploader->setAllowRenameFiles(true);
        $mediaDirectory = $this->fileSystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        $destinationPath = $mediaDirectory->getAbsolutePath($targetDirectory);
        $result = $uploader->save($destinationPath);
        
        if ($result['file']) {
            $filePath = $result['path'] . $result['file'];
            $fileName = $result['name'];
            $uploadedFileName = $result['file'];
        }

        /** @see \Magento\Contact\Controller\Index\Post::validatedParams() */
        $replyToName = !empty($variables['data']['buyer_first_and_last_name']) ? $variables['data']['buyer_first_and_last_name'] : null;
        $this->inlineTranslation->suspend();
        $sender = [
            'email' => 'alexandar.gordic@gmail.com',
            'name' => 'Admin'
        ];
        $bccEmail = "info@e-mall.rs";
        try {
        
            $mimeType = mime_content_type($filePath);

                $transport = $this->transportBuilder
                    ->setTemplateIdentifier('email_complaint_template')
                    ->setTemplateOptions(
                        [
                            'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                            'store' => $this->storemanager->getStore()->getId()
                        ]
                    )
                    ->addAttachment($this->file->read($filePath), $fileName, $mimeType)
                    ->setTemplateVars($variables)
                    ->setFrom($sender)
                    ->addTo($replyTo)
                    ->addBcc($bccEmail)
                    ->getTransport();

            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }   
    }
    /**
     * Method to validated params.
     *
     * @return array
     * @throws \Exception
     */
    private function validatedParams()
    {
        $request = $this->getRequest()->getPostValue();

        if (trim($request['buyer_first_and_last_name']) === '') {
            throw new LocalizedException(__('Enter the Name and try again.'));
        }
        if (trim($request['Describe_the_problemcomment']) === '') {
            throw new LocalizedException(__('Enter the comment and try again.'));
        }
        if (\strpos($request['email'], '@') === false) {
            throw new LocalizedException(__('The email address is invalid. Verify the email address and try again.'));
        }
        if (trim($request['hideit']) !== '') {
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new \Exception();
        }
        return $request;
    }

}
