<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Atama\Share\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Cart\CustomerCartResolver;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForCustomer;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForGuest;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\GuestCart\GuestCartResolver;
use Magento\Checkout\Model\Session;
use Magento\Framework\Session\Config as SessionConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use \Psr\Log\LoggerInterface;

class SessionCart implements ResolverInterface
{

    /**
     * @var CreateEmptyCartForCustomer
     */
    private $createEmptyCartForCustomer;

    /**
     * @var CreateEmptyCartForGuest
     */
    private $createEmptyCartForGuest;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var GuestCartResolver
     */
    private $guestCartResolver;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

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
     * @var GetCartForUser
     */
    private $getCartForUser;
    /**
     * @var CustomerCartResolver
     */
    private $customerCartResolver;




    /**
     * @param CreateEmptyCartForCustomer $createEmptyCartForCustomer
     * @param CreateEmptyCartForGuest $createEmptyCartForGuest
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param GuestCartResolver $guestCartResolver
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param Session $session
     * @param SessionManagerInterface $sessionManager
     * @param LoggerInterface $logger
     * @param GetCartForUser $getCartForUser
     * @param CustomerCartResolver $customerCartResolver
     */
    public function __construct(
        CreateEmptyCartForCustomer $createEmptyCartForCustomer,
        CreateEmptyCartForGuest $createEmptyCartForGuest,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        GuestCartResolver $guestCartResolver,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        Session $session,
        SessionManagerInterface $sessionManager,
        LoggerInterface $logger,
        GetCartForUser $getCartForUser,
        CustomerCartResolver $customerCartResolver
    ) {
        $this->createEmptyCartForCustomer = $createEmptyCartForCustomer;
        $this->createEmptyCartForGuest = $createEmptyCartForGuest;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->guestCartResolver = $guestCartResolver;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->session = $session;
        $this->sessionManager = $sessionManager;
        $this->logger = $logger;
        $this->getCartForUser = $getCartForUser;
        $this->customerCartResolver = $customerCartResolver;
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

            $this->logger->error($this->session->getSessionId());
            $this->logger->error($this->sessionManager->getSessionId());
            $this->logger->error($this->session->getQuoteId());
            $this->logger->error(print_r($this->session->getData(), true));
            $cartId = null;
            $maskedCartId = null;
            if (is_numeric($guestQuoteId) && is_int(intval($guestQuoteId))) {
                $maskedCartId = $this->quoteIdToMaskedQuoteId->execute(intval($guestQuoteId) );
                $cart = $this->getCartForUser->execute($maskedCartId, null, $storeId);
            }

            return [
                "cart_id" => $maskedCartId,
                "registered_customer" => false,
                "total_quantity" => $cart ? $cart->getItemsQty() : 0
            ];
        } else {
            $currentUserId = $context->getUserId();
            $cart = $this->customerCartResolver->resolve($currentUserId);
            $maskedCartId = $this->quoteIdToMaskedQuoteId->execute(intval($cart->getId()));
            return [
                "cart_id" => $maskedCartId,
                "registered_customer" => true,
                "total_quantity" => $cart->getItemsQty()
            ];
        }

    }

}

