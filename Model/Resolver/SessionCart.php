<?php
declare(strict_types=1);

namespace Atama\Share\Model\Resolver;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Cart\CustomerCartResolver;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Session\Config as SessionConfig;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use \Psr\Log\LoggerInterface;
use Magento\GraphQl\Model\Query\ContextInterface;

class SessionCart implements ResolverInterface
{

    private const COOKIE_LIFETIME_CONFIG_PATH = 'web/cookie/cookie_lifetime';

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var GetCartForUser
     */
    private $getCartForUser;
    /**
     * @var CustomerCartResolver
     */
    private $customerCartResolver;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;


    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param Session $session
     * @param LoggerInterface $logger
     * @param GetCartForUser $getCartForUser
     * @param CustomerCartResolver $customerCartResolver
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        Session $session,
        LoggerInterface $logger,
        GetCartForUser $getCartForUser,
        CustomerCartResolver $customerCartResolver,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->session = $session;
        $this->logger = $logger;
        $this->getCartForUser = $getCartForUser;
        $this->customerCartResolver = $customerCartResolver;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = null;
        /**
         * @var ContextInterface $context
         */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            $guestQuoteId = $this->session->getQuoteId();

            $cartId = null;
            $maskedCartId = null;
            if (is_numeric($guestQuoteId) && is_int(intval($guestQuoteId))) {
                $maskedCartId = $this->quoteIdToMaskedQuoteId->execute(intval($guestQuoteId) );
                $cart = $this->getCartForUser->execute($maskedCartId, null, $storeId);
            }

            return [
                "cart_id" => $maskedCartId,
                "registered_customer" => false,
                "total_quantity" => $cart ? $cart->getItemsQty() : 0,
                "session_cookie_lifetime" => $this->getSessionLife()
            ];
        } else {
            $currentUserId = $context->getUserId();
            $cart = $this->customerCartResolver->resolve($currentUserId);
            $maskedCartId = $this->quoteIdToMaskedQuoteId->execute(intval($cart->getId()));
            return [
                "cart_id" => $maskedCartId,
                "registered_customer" => true,
                "total_quantity" => $cart->getItemsQty(),
                "session_cookie_lifetime" => $this->getSessionLife()
            ];
        }

    }

    private function getSessionLife()
    {
        return $this->scopeConfig->getValue(self::COOKIE_LIFETIME_CONFIG_PATH);
    }

}

