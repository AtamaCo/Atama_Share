<?php
declare(strict_types=1);

namespace Atama\Share\Model\Resolver;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Integration\Api\UserTokenIssuerInterface;
use Magento\Integration\Model\UserToken\UserTokenParametersFactory;
use Magento\Integration\Api\Exception\UserTokenException;
use Magento\Integration\Api\UserTokenRevokerInterface;
use Magento\Integration\Model\CustomUserContext;
use Psr\Log\LoggerInterface;

class CreateSessionCustomerToken implements ResolverInterface
{

    /**
     * @var UserTokenParametersFactory
     */
    private $tokenParametersFactory;

    /**
     * @var UserTokenIssuerInterface
     */
    private $tokenIssuer;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;
    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param Session $session
     * @param SessionManagerInterface $sessionManager
     * @param LoggerInterface $logger
     * @param UserTokenParametersFactory|null $tokenParamsFactory
     * @param UserTokenIssuerInterface|null $tokenIssuer
     */
    public function __construct(
        Session                    $session,
        SessionManagerInterface    $sessionManager,
        LoggerInterface            $logger,
        ?UserTokenParametersFactory $tokenParamsFactory = null,
        ?UserTokenIssuerInterface $tokenIssuer = null
    )
    {
        $this->tokenParametersFactory = $tokenParamsFactory
            ?? ObjectManager::getInstance()->get(UserTokenParametersFactory::class);
        $this->tokenIssuer = $tokenIssuer ?? ObjectManager::getInstance()->get(UserTokenIssuerInterface::class);
        $this->session = $session;
        $this->sessionManager = $sessionManager;
        $this->logger = $logger;
    }


    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        throw new GraphQlAuthenticationException("Unavailable");
//        $customerId = $context->getUserId();
//
//        if ($customerId) {
//            throw new GraphQlAuthenticationException("Logged out");
//        }
//
//        $context = new CustomUserContext(
//            (int)$customerId,
//            CustomUserContext::USER_TYPE_CUSTOMER
//        );
//        $params = $this->tokenParametersFactory->create();
//
//        $token =  $this->tokenIssuer->create($context, $params);
//
//        return $token;
    }


}

