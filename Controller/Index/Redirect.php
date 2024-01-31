<?php
declare(strict_types=1);

namespace Atama\Share\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Redirect implements HttpGetActionInterface
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
     * @var RedirectFactory
     */
    protected $redirectFactory;

    /**
     * Constructor
     *
     * @param PageFactory $resultPageFactory
     * @param ResultFactory $resultFactory
     */
    public function __construct(PageFactory $resultPageFactory, ResultFactory $resultFactory, RedirectFactory $redirectFactory)
    {

        $this->resultPageFactory = $resultPageFactory;
        $this->resultFactory = $resultFactory;
        $this->redirectFactory = $redirectFactory;
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->redirectFactory->create();
        $resultRedirect->setPath('checkout/cart');
        return $resultRedirect;
    }
}

