<?php

declare(strict_types=1);

namespace Atama\Share\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Integration\Api\UserTokenIssuerInterface;
use Magento\Integration\Model\CustomUserContext;
use Magento\Quote\Model\GuestCart\GuestCartResolver;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Checkout\Model\Session;
use Magento\Integration\Model\UserToken\UserTokenParametersFactory;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForCustomer;
use Magento\GraphQl\Model\Query\ContextInterface;

/**
 * Responsible for providing data for a new "side-by-side" customer data section.
 *
 * This class handles both guest and registered customers and will provide the cart id
 * and (in the case of registered customers) a bearer token that can be used to authenticate
 * graphql requests coming from sources outside of Magento.
 */
class SideBySide implements SectionSourceInterface
{
    private const SIDE_BY_SIDE_TOKEN_ENABLED_PATH = 'web/edge_delivery_service/side_by_side_section_token_enable';
    private const SIDE_BY_SIDE_CART_CREATION_ENABLED_PATH = 'web/edge_delivery_service/side_by_side_section_create_cart_enable';

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $session;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var GuestCartResolver
     */
    private $guestCartResolver;
    /**
     * @var CurrentCustomer
     */
    private $currentCustomer;
    /**
     * @var CreateEmptyCartForCustomer
     */
    private $createEmptyCartForCustomer;
    /**
     * @var UserTokenParametersFactory
     */
    private $tokenParametersFactory;
    /**
     * @var UserTokenIssuerInterface
     */
    private $tokenIssuer;



    /**
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param Session $session
     * @param ScopeConfigInterface $scopeConfig
     * @param CurrentCustomer $currentCustomer
     * @param GuestCartResolver $guestCartResolver
     * @param CreateEmptyCartForCustomer $createEmptyCartForCustomer
     * @param UserTokenParametersFactory|null $tokenParamsFactory
     * @param UserTokenIssuerInterface|null $tokenIssuer
     */
    public function __construct(
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        Session $session,
        ScopeConfigInterface $scopeConfig,
        CurrentCustomer $currentCustomer,
        GuestCartResolver $guestCartResolver,
        CreateEmptyCartForCustomer $createEmptyCartForCustomer,
        ?UserTokenParametersFactory $tokenParamsFactory = null,
        ?UserTokenIssuerInterface $tokenIssuer = null,
    ) {
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->session = $session;
        $this->scopeConfig = $scopeConfig;
        $this->currentCustomer = $currentCustomer;
        $this->guestCartResolver = $guestCartResolver;
        $this->createEmptyCartForCustomer = $createEmptyCartForCustomer;
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
            } else if($this->isCartCreationEnabled()) {
                $guestQuote = $this->guestCartResolver->resolve();
                $guestQuoteId = is_numeric($guestQuote->getId()) && is_int(intval($guestQuote->getId())) ? intval($guestQuote->getId()) : null;
                $this->session->setQuoteId($guestQuote->getId());
                $this->session->setCartWasUpdated(true);
                $maskedCartId = $this->quoteIdToMaskedQuoteId->execute($guestQuoteId);
            }

            return [
                "cart_id" => $maskedCartId,
            ];
        } else {
            $currentCustomerId = $this->currentCustomer->getCustomer()->getId();

            $maskedCartId = null;
            if ($this->session->getQuoteId() !== null && $this->session->getQuoteId() !== 0) {
                $maskedCartId = $this->quoteIdToMaskedQuoteId->execute(intval($this->session->getQuoteId()));
            } else if ($this->isCartCreationEnabled()) {
                $maskedCartId = $this->createEmptyCartForCustomer->execute($currentCustomerId);
            }


            return [
                "cart_id" => $maskedCartId,
                "token" => $this->isTokenCreationEnabled() ? $this->generateCustomerToken(intval($currentCustomerId)) : null,
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

    private function isTokenCreationEnabled()
    {
        return $this->scopeConfig->getValue(self::SIDE_BY_SIDE_TOKEN_ENABLED_PATH) === "1";
    }

    private function isCartCreationEnabled()
    {
        return $this->scopeConfig->getValue(self::SIDE_BY_SIDE_CART_CREATION_ENABLED_PATH) === "1";
    }
}
