<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Atama\Share\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * Constructor
     *
     * @param PageFactory $resultPageFactory
     * @param ResultFactory $resultFactory
     */
    public function __construct(PageFactory $resultPageFactory, ResultFactory $resultFactory)
    {

        $this->resultPageFactory = $resultPageFactory;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        /** @var Raw $rawResult */
        $rawResult = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $rawResult->setHeader("Content-Type", "image/svg+xml");
        return $rawResult->setContents('<?xml version="1.0" standalone="no"?>
            <!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN"
            "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
            <svg width="1px" height="1px" version="1.1" xmlns="http://www.w3.org/2000/svg">
            <circle cx="1" cy="1" r="1" fill="white"/>
            </svg>');
    }
}

