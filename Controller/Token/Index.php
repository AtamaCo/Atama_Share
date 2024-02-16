<?php
declare(strict_types=1);

namespace Atama\Share\Controller\Token;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Integration\Api\UserTokenIssuerInterface;
use Magento\Integration\Model\CustomUserContext;
use Magento\Integration\Model\UserToken\UserTokenParametersFactory;

/**
 * Route handler responsible for generating a customer JWT for authenticating graphql requests.
 * This route needs to be enabled via configuration.
 */
class Index implements HttpPostActionInterface
{

    private const SHARE_SESSION_TOKEN_ROUTE_ENABLED_PATH = 'web/edge_delivery_service/side_by_side_token_route_enable';

    /**
     * @var CustomerSession
     */
    protected $customerSession;
    /**
     * @var UserTokenParametersFactory
     */
    private $tokenParametersFactory;
    /**
     * @var UserTokenIssuerInterface
     */
    private $tokenIssuer;
    /**
     * @var ResultFactory
     */
    protected $resultFactory;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;


    /**
     * Constructor
     *
     * @param CustomerSession $customerSession
     * @param ResultFactory $resultFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param UserTokenParametersFactory|null $tokenParamsFactory
     * @param UserTokenIssuerInterface|null $tokenIssuer
     */
    public function __construct(
        CustomerSession $customerSession,
        ResultFactory $resultFactory,
        ScopeConfigInterface $scopeConfig,
        ?UserTokenParametersFactory $tokenParamsFactory = null,
        ?UserTokenIssuerInterface $tokenIssuer = null)
    {
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->tokenParametersFactory = $tokenParamsFactory
            ?? ObjectManager::getInstance()->get(UserTokenParametersFactory::class);
        $this->tokenIssuer = $tokenIssuer ?? ObjectManager::getInstance()->get(UserTokenIssuerInterface::class);
        $this->resultFactory = $resultFactory;
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        // If not enabled in config return a not found type response
        if (!$this->isSessionTokenRouteEnabled()) {
            $resultForward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
            $resultForward->forward('noroute');
            return $resultForward;
        }

        $currentCustomerId = $this->customerSession->getId();

        $token = null;
        if (!empty($currentCustomerId) && intval($currentCustomerId) > 0) {
            $context = new CustomUserContext(
                intval($currentCustomerId),
                CustomUserContext::USER_TYPE_CUSTOMER
            );
            $params = $this->tokenParametersFactory->create();
            $token = $this->tokenIssuer->create($context, $params);
        }

        $rawResult = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $rawResult->setData([
            'token' => $token
        ]);
       return $rawResult;
    }

    private function isSessionTokenRouteEnabled()
    {
        return $this->scopeConfig->getValue(self::SHARE_SESSION_TOKEN_ROUTE_ENABLED_PATH) === "1";
    }

}
