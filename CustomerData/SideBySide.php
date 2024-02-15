<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Atama\Share\CustomerData;


use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Integration\Api\UserTokenIssuerInterface;
use Magento\Integration\Model\CustomUserContext;
use Magento\Quote\Model\Cart\CustomerCartResolver;
use Magento\Quote\Model\GuestCart\GuestCartResolver;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Session\Config as SessionConfig;
use Magento\Integration\Model\UserToken\UserTokenParametersFactory;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForCustomer;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForGuest;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use \Psr\Log\LoggerInterface;
use Magento\GraphQl\Model\Query\ContextInterface;

/**
 * Returns information for "Recently Ordered" widget.
 * It contains list of 5 salable products from the last placed order.
 * Qty of products to display is limited by LastOrderedItems::SIDEBAR_ORDER_LIMIT constant.
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class SideBySide implements SectionSourceInterface
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
     * @var \Magento\Checkout\Model\Session
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
     * @var GuestCartResolver
     */
    private $guestCartResolver;

    /**
     * @var \Magento\Customer\Helper\Session\CurrentCustomer
     */
    private $currentCustomer;

    /**
     * @var CreateEmptyCartForCustomer
     */
    private $createEmptyCartForCustomer;

    /**
     * @var CreateEmptyCartForGuest
     */
    private $createEmptyCartForGuest;

    /**
     * @var UserTokenParametersFactory
     */
    private $tokenParametersFactory;
    /**
     * @var UserTokenIssuerInterface
     */
    private $tokenIssuer;



    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param Session $session
     * @param LoggerInterface $logger
     * @param GetCartForUser $getCartForUser
     * @param CustomerCartResolver $customerCartResolver
     * @param ScopeConfigInterface $scopeConfig
     * @param CurrentCustomer $currentCustomer
     * @param GuestCartResolver $guestCartResolver
     * @param CurrentCustomerTokenParam $currentCustomer
     * @param CreateEmptyCartForCustomer $createEmptyCartForCustomer
     * @param CreateEmptyCartForGuest $createEmptyCartForGuest
     * @param UserTokenParametersFactory|null $tokenParamsFactory
     * @param UserTokenIssuerInterface|null $tokenIssuer
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        Session $session,
        LoggerInterface $logger,
        GetCartForUser $getCartForUser,
        CustomerCartResolver $customerCartResolver,
        ScopeConfigInterface $scopeConfig,
        CurrentCustomer $currentCustomer,
        GuestCartResolver $guestCartResolver,
        CreateEmptyCartForCustomer $createEmptyCartForCustomer,
        CreateEmptyCartForGuest $createEmptyCartForGuest,
        ?UserTokenParametersFactory $tokenParamsFactory = null,
        ?UserTokenIssuerInterface $tokenIssuer = null,
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->session = $session;
        $this->logger = $logger;
        $this->getCartForUser = $getCartForUser;
        $this->customerCartResolver = $customerCartResolver;
        $this->scopeConfig = $scopeConfig;
        $this->currentCustomer = $currentCustomer;
        $this->guestCartResolver = $guestCartResolver;

        $this->createEmptyCartForCustomer = $createEmptyCartForCustomer;
        $this->createEmptyCartForGuest = $createEmptyCartForGuest;
        $this->tokenParametersFactory = $tokenParamsFactory
            ?? ObjectManager::getInstance()->get(UserTokenParametersFactory::class);
        $this->tokenIssuer = $tokenIssuer ?? ObjectManager::getInstance()->get(UserTokenIssuerInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function getSectionData(): array
    {
        $cart = null;
        /**
         * @var ContextInterface $context
         */
        if (null === $this->currentCustomer->getCustomerId()) {
            $guestQuoteId = $this->session->getQuoteId();

            $cartId = null;
            $maskedCartId = null;
            if (is_numeric($guestQuoteId) && is_int(intval($guestQuoteId))) {
                $maskedCartId = $this->quoteIdToMaskedQuoteId->execute(intval($guestQuoteId));
            } else {
                $guestQuote = $this->guestCartResolver->resolve();
                $guestQuoteId = is_numeric($guestQuote->getId()) && is_int(intval($guestQuote->getId())) ? intval($guestQuote->getId()) : null;
                $this->session->setQuoteId($guestQuote->getId());
                $this->session->setCartWasUpdated(true);
                $maskedCartId = $this->quoteIdToMaskedQuoteId->execute($guestQuoteId);
            }

            return [
                "cart_id" => $maskedCartId,
                "registered_customer" => false,
            ];
        } else {
            $currentCustomerId = $this->currentCustomer->getCustomer()->getId();

            $maskedCartId = null;
            if ($this->session->getQuoteId() !== null && $this->session->getQuoteId() !== 0) {
                $maskedCartId = $this->quoteIdToMaskedQuoteId->execute(intval($this->session->getQuoteId()));
            } else {
                $maskedCartId = $this->createEmptyCartForCustomer->execute($currentCustomerId);
            }


            return [
                "cart_id" => $maskedCartId,
                "registered_customer" => true,
                "token" => $this->generateCustomerToken(intval($currentCustomerId))
            ];
        }

    }

    private function generateCustomerToken(int $customerId): string {
        $context = new CustomUserContext(
            $customerId,
            CustomUserContext::USER_TYPE_CUSTOMER
        );
        $params = $this->tokenParametersFactory->create();

        return $this->tokenIssuer->create($context, $params);
    }

    private function getSessionLife()
    {
        return $this->scopeConfig->getValue(self::COOKIE_LIFETIME_CONFIG_PATH);
    }
}
