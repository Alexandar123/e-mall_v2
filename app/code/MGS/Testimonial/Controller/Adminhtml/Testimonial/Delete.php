<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace MGS\Testimonial\Controller\Adminhtml\Testimonial;

use Magento\Backend\App\Action;

class Delete extends \MGS\Testimonial\Controller\Adminhtml\Testimonial
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
		$resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');
		if ($id) {
            try {
                $model = $this->_objectManager->create('MGS\Testimonial\Model\Testimonial');
                $model->setId($id);
                $model->load($id);
				$title =  $model->getTitle();
				$model->delete();
				$this->messageManager->addSuccess(__('You deleted the item "%1".', $title));
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());
            }
        }
        return $resultRedirect->setPath('*/*/');
    }
}
