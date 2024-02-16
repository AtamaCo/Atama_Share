<?php
declare(strict_types=1);

namespace Atama\Share\Controller\Cart;

use Magento\Checkout\Model\Session;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Model\GuestCart\GuestCartResolver;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForCustomer;

/**
 * Route handler for returning the current session cart for either guest or customer, and creating
 * one if needed. This route needs to be enabled via config.
 */
class Index implements HttpPostActionInterface
{

    private const SHARE_SESSION_CREATE_CART_ROUTE_ENABLED_PATH = 'web/edge_delivery_service/side_by_side_create_cart_route_enable';
    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $session;
    /**
     * @var ResultFactory
     */
    protected $resultFactory;
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
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param Session $session
     * @param ResultFactory $resultFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param CurrentCustomer $currentCustomer
     * @param GuestCartResolver $guestCartResolver
     * @param CreateEmptyCartForCustomer $createEmptyCartForCustomer
     */
    public function __construct(
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        Session $session,
        ResultFactory $resultFactory,
        ScopeConfigInterface $scopeConfig,
        CurrentCustomer $currentCustomer,
        GuestCartResolver $guestCartResolver,
        CreateEmptyCartForCustomer $createEmptyCartForCustomer,
    ) {
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->session = $session;
        $this->resultFactory = $resultFactory;
        $this->scopeConfig = $scopeConfig;
        $this->currentCustomer = $currentCustomer;
        $this->guestCartResolver = $guestCartResolver;
        $this->createEmptyCartForCustomer = $createEmptyCartForCustomer;
    }
    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        // If not enabled in config return a not found type response
        if (!$this->isSessionCreateCartRouteEnabled()) {
            $resultForward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
            $resultForward->forward('noroute');
            return $resultForward;
        }

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
        } else {
            $currentCustomerId = $this->currentCustomer->getCustomer()->getId();

            $maskedCartId = null;
            if ($this->session->getQuoteId() !== null && $this->session->getQuoteId() !== 0) {
                $maskedCartId = $this->quoteIdToMaskedQuoteId->execute(intval($this->session->getQuoteId()));
            } else {
                $maskedCartId = $this->createEmptyCartForCustomer->execute($currentCustomerId);
            }
        }

        $rawResult = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $rawResult->setData([
            'cart_id' => $maskedCartId
        ]);
       return $rawResult;
    }

    private function isSessionCreateCartRouteEnabled()
    {
        return $this->scopeConfig->getValue(self::SHARE_SESSION_CREATE_CART_ROUTE_ENABLED_PATH) === "1";
    }

}
