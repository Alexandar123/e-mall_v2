<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
 
namespace MGS\Fbuilder\Controller\Index;

use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Cache\Manager as CacheManager;

class Newsection extends \Magento\Framework\App\Action\Action
{
    protected $_storeManager;
    
    /**
     * Url Builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        \Magento\Framework\View\Element\Context $urlContext,
        CacheManager $cacheManager
    ) {
        $this->_storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->_urlBuilder = $urlContext->getUrlBuilder();
        $this->cacheManager = $cacheManager;

        parent::__construct($context);
    }
    
    public function getModel($model)
    {
        return $this->_objectManager->create($model);
    }
    
    public function execute()
    {
        if ($this->getRequest()->isAjax() && ($this->customerSession->getUseFrontendBuilder() == 1)) {
            $storeId = $this->_storeManager->getStore()->getId();
            $lastSection = $this->getModel('MGS\Fbuilder\Model\Section')->getCollection()->addFieldToFilter('store_id', $storeId)->setOrder('block_position', 'DESC')->getFirstItem();
            if ($lastSection->getId()) {
                $lastPosition = $lastSection->getBlockPosition() + 1;
            } else {
                $lastPosition = 1;
            }
            
            $section = $this->getModel('MGS\Fbuilder\Model\Section');
            $section->setBlockCols(12)->setStoreId($storeId)->setBlockPosition($lastPosition);
            if ($pageId = $this->getRequest()->getParam('page_id')) {
                $pageType = $this->getRequest()->getParam('page_type');
                if ($pageType=='cms') {
                    $section->setPageId($pageId)->setPageType('cms');
                } elseif ($pageType=='category') {
                    $section->setProductId($pageId)->setPageType('category');
                    if ($this->getRequest()->getParam('override')==1) {
                        $section->setStoreId($storeId);
                    } else {
                        $section->setStoreId(0);
                    }
                } else {
                    $section->setProductId($pageId)->setPageType('product');
                    if ($this->getRequest()->getParam('page_type')==1) {
                        $section->setStoreId($storeId);
                    } else {
                        $section->setStoreId(0);
                    }
                }
            }
            try {
                $section->save();
                $name = 'block'.$section->getId();
                $section->setName($name)->save();
                $html = '<section class="builder-container section-builder sort-item empty-section" id="panel-section-'.$section->getId().'">
					<div class="frame container-panel no-padding">
						<div class="edit-panel parent-panel">
							<ul>
								<li class="up-link"><a class="moveuplink" href="#" onclick="return false;" title="Move Up"><em class="fa fa-arrow-up">&nbsp;</em></a></li>
								<li class="down-link"><a class="movedownlink" href="#" onclick="return false;" title="Move Down"><em class="fa fa-arrow-down">&nbsp;</em></a></li>
								<li><a title="'.__('Edit').'" class="popup-link" href="'.$this->_urlBuilder->getUrl('fbuilder/edit/section', ['id'=>$section->getId()]).'"><em class="fa fa-gear"></em></a></li>
								<li><a onclick="if(confirm(\''.__('Are you sure you would like to duplicate this section?').'\')) setLocation(\'http://gpm.arrowhitech.net/fbuilder/fbuilder/index/duplicate/id/'.$section->getId().'/\'); return false" title="Duplicate Section" href="#"><em class="fa fa fa-copy"></em></a></li>
								<li><a onclick="if(confirm(\''.__('Are you sure you would like to remove this section?').'\')) removeSection('.$section->getId().'); return false" title="Delete Section" href="#"><em class="fa fa-close"></em></a></li>
							</ul>
						</div>															
						<div class="line">
							<div class="col-des-12 col-builder">
								<div class="line content-panel empty-block">
									<div class="add-new-block col-des-12">';
                                    
                if ($this->getRequest()->getParam('page_id')) {
                    if ($this->getRequest()->getParam('page_type')=='cms') {
                        $url = $this->_urlBuilder->getUrl('fbuilder/create/block', ['page_id'=>$pageId,'name'=>$name.'-0', 'page_type'=>'cms']);
                        $html .= '<button class="action" type="button" onclick="openPopup(\''.$url.'\')" data-page-type="cms" data-page-id="'.$pageId .'" data-block-name="'.$name.'-0"><em class="fa fa-plus-circle"></em>&nbsp;'.__('Add New Block').'</button>';
                                                
                        if ($this->customerSession->getBlockCopied()) {
                            $html .= '&nbsp;<button class="action btn btn-default btn-dulicate" type="button" onclick="pasteBlock('.$pageId.', \''.$name.'-0\',\'cms\')"><em class="fa fa-plus-circle"></em>&nbsp;'.__('Paste Copied Block').'</button>';
                        }
                    } elseif ($this->getRequest()->getParam('page_type')=='category') {
                        $url = $this->_urlBuilder->getUrl('fbuilder/create/block', ['product_id'=>$pageId,'name'=>$name.'-0', 'page_type'=>'category','overriden'=>$this->getRequest()->getParam('override')]);
                        $html .= '<button class="action" type="button" onclick="openPopup(\''.$url.'\')" data-page-type="product" data-page-id="'.$pageId .'" data-block-name="'.$name.'-0"><em class="fa fa-plus-circle"></em>&nbsp;'.__('Add New Block').'</button>';
                                                
                        if ($this->customerSession->getBlockCopied()) {
                            $html .= '&nbsp;<button class="action btn btn-default btn-dulicate" type="button" onclick="pasteBlock('.$pageId.', \''.$name.'-0\',\'category\')"><em class="fa fa-plus-circle"></em>&nbsp;'.__('Paste Copied Block').'</button>';
                        }
                    } else {
                        $url = $this->_urlBuilder->getUrl('fbuilder/create/block', ['product_id'=>$pageId,'name'=>$name.'-0', 'page_type'=>'product','overriden'=>$this->getRequest()->getParam('page_type')]);
                        $html .= '<button class="action" type="button" onclick="openPopup(\''.$url.'\')" data-page-type="product" data-page-id="'.$pageId .'" data-block-name="'.$name.'-0"><em class="fa fa-plus-circle"></em>&nbsp;'.__('Add New Block').'</button>';
                                                
                        if ($this->customerSession->getBlockCopied()) {
                            $html .= '&nbsp;<button class="action btn btn-default btn-dulicate" type="button" onclick="pasteBlock('.$pageId.', \''.$name.'-0\',\'product\')"><em class="fa fa-plus-circle"></em>&nbsp;'.__('Paste Copied Block').'</button>';
                        }
                    }
                } else {
                    $url = $this->_urlBuilder->getUrl('fbuilder/create/block', ['name'=>$name.'-0']);
                    $html .= '<button class="action" type="button" onclick="openPopup(\''.$url.'\')"><em class="fa fa-plus-circle"></em>&nbsp;'.__('Add New Block').'</button>';
                }
                                        
                                        $html .= '</div>
								</div>
							</div>
						</div>
					</div>
				</section>';
                
                $this->cacheManager->clean(['full_page']);
                return $this->getResponse()->setBody($html);
            } catch (Exception $e) {
                return $this->getResponse()->setBody($e->getMessage());
            }
            return;
        } else {
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }
    }
}
