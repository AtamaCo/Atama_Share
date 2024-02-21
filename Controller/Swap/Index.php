<?php
declare(strict_types=1);

namespace Atama\Share\Controller\Swap;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AuthenticationInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Integration\Api\Exception\UserTokenException;
use Magento\Integration\Api\UserTokenIssuerInterface;
use Magento\Integration\Api\UserTokenReaderInterface;
use Magento\Integration\Api\UserTokenValidatorInterface;
use Magento\Integration\Model\CustomUserContext;
use Magento\Integration\Model\UserToken\UserTokenParametersFactory;
use Magento\Framework\App\Action\Context;

/**
 * Route handler responsible for swapping a bearer token for a real life Magento PHP customer session.
 */
class Index extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{

    private const SHARE_TOKEN_SESSION_SWAP_ROUTE_ENABLED_PATH = 'web/edge_delivery_service/side_by_side_token_route_enable';

    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    /**
     * @var AuthenticationInterface
     */
    private $authentication;

    /**
     * @var Session
     */
    protected $session;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var AccountManagementInterface
     */
    private $accountManagement;
    /**
     * @var UserTokenReaderInterface
     */
    private $userTokenReader;
    /**
     * @var UserTokenValidatorInterface
     */
    private $userTokenValidator;
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
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param Session $session
     * @param ResultFactory $resultFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param AuthenticationInterface $authentication
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface $accountManagement
     * @param UserTokenParametersFactory|null $tokenParamsFactory
     * @param UserTokenIssuerInterface|null $tokenIssuer
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        Session $session,
        ResultFactory $resultFactory,
        ScopeConfigInterface $scopeConfig,
        AuthenticationInterface $authentication,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $accountManagement,
        ?UserTokenReaderInterface $tokenReader = null,
        ?UserTokenValidatorInterface $tokenValidator = null
    )
    {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->session = $session;
        $this->scopeConfig = $scopeConfig;
        $this->authentication = $authentication;
        $this->customerRepository = $customerRepository;
        $this->accountManagement = $accountManagement;
        $this->userTokenReader = $tokenReader ?? ObjectManager::getInstance()->get(UserTokenReaderInterface::class);
        $this->userTokenValidator = $tokenValidator
            ?? ObjectManager::getInstance()->get(UserTokenValidatorInterface::class);
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
        if (!$this->isTokenSwapRouteEnabled()) {
            $resultForward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
            $resultForward->forward('noroute');
            return $resultForward;
        }

        $rawResult = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if ($this->session->isLoggedIn()) {
            $rawResult->setData([
                'loggedIn' => true
            ]);
            return $rawResult;
        }

        $content = $this->_request->getContent();
        $json = json_decode($content);
        $bearerToken = $json->token;

        try {
            $token = $this->userTokenReader->read($bearerToken);
        } catch (UserTokenException $exception) {
            $rawResult->setData([
                'loggedIn' => false,
                'error' => true,
            ]);
            return $rawResult;
        }
        try {
            $this->userTokenValidator->validate($token);
        } catch (AuthorizationException $exception) {
            $rawResult->setData([
                'loggedIn' => false,
                'error' => true,
            ]);
            return $rawResult;
        }

        $userId = $token->getUserContext()->getUserId();

        $customer = $this->loadCustomer($userId);

        if ($customer === null) {
            $rawResult->setData([
                'loggedIn' => false,
                'error' => true,
            ]);
            return $rawResult;
        }

        $this->session->setCustomerDataAsLoggedIn($customer);

        $rawResult->setData([
            'loggedIn' => true,
        ]);
        return $rawResult;

    }

    private function loadCustomer($userId) {

        try {
            $customer = $this->customerRepository->getById($userId);
        } catch (NoSuchEntityException $e) {
            return false;
        } catch (LocalizedException $e) {
            return false;
        }

        if (true === $this->authentication->isLocked($userId)) {
            return false;
        }

        try {
            $confirmationStatus = $this->accountManagement->getConfirmationStatus($userId);
        } catch (LocalizedException $e) {
            return false;
        }

        if ($confirmationStatus === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
            return false;
        }
        return $customer;
    }

    private function isTokenSwapRouteEnabled()
    {
        return true;
        return $this->scopeConfig->getValue(self::SHARE_TOKEN_SESSION_SWAP_ROUTE_ENABLED_PATH) === "1";
    }

}
