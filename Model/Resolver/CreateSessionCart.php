<?php
declare(strict_types=1);

namespace Atama\Share\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForCustomer;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForGuest;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\GuestCart\GuestCartResolver;
use Magento\Checkout\Model\Session;
use Magento\Framework\Session\Config as SessionConfig;
use Magento\Framework\Session\SessionManagerInterface;
use \Psr\Log\LoggerInterface;

class CreateSessionCart implements ResolverInterface
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
     * @param CreateEmptyCartForCustomer $createEmptyCartForCustomer
     * @param CreateEmptyCartForGuest $createEmptyCartForGuest
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param GuestCartResolver $guestCartResolver
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param Session $session
     * @param SessionManagerInterface $sessionManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        CreateEmptyCartForCustomer $createEmptyCartForCustomer,
        CreateEmptyCartForGuest $createEmptyCartForGuest,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        GuestCartResolver $guestCartResolver,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        Session $session,
        SessionManagerInterface $sessionManager,
        LoggerInterface $logger
    ) {
        $this->createEmptyCartForCustomer = $createEmptyCartForCustomer;
        $this->createEmptyCartForGuest = $createEmptyCartForGuest;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->guestCartResolver = $guestCartResolver;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->session = $session;
        $this->sessionManager = $sessionManager;
        $this->logger = $logger;
    }


    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $customerId = $context->getUserId();

        $predefinedMaskedQuoteId = null;
        if (isset($args['input']['cart_id'])) {
            $predefinedMaskedQuoteId = $args['input']['cart_id'];
            $this->validateMaskedId($predefinedMaskedQuoteId);
        }

        if (0 === $customerId || null === $customerId) {
            $candidateQuoteId = $this->session->getQuoteId();
            $guestQuote = $this->guestCartResolver->resolve($predefinedMaskedQuoteId);
            $guestQuoteId = is_numeric($guestQuote->getId()) && is_int(intval($guestQuote->getId())) ? intval($guestQuote->getId()) : null;
            $this->session->setQuoteId($guestQuote->getId());
            $this->session->setCartWasUpdated(true);
            $this->sessionManager->writeClose();
            return $this->quoteIdToMaskedQuoteId->execute($guestQuoteId);
        }

        return $this->createEmptyCartForCustomer->execute($customerId, $predefinedMaskedQuoteId);
    }


    /**
     * Validate masked id
     *
     * @param string $maskedId
     * @throws GraphQlAlreadyExistsException
     * @throws GraphQlInputException
     */
    private function validateMaskedId(string $maskedId): void
    {
        if (mb_strlen($maskedId) != 32) {
            throw new GraphQlInputException(__('Cart ID length should to be 32 symbols.'));
        }

        if ($this->isQuoteWithSuchMaskedIdAlreadyExists($maskedId)) {
            throw new GraphQlAlreadyExistsException(__('Cart with ID "%1" already exists.', $maskedId));
        }
    }

    /**
     * Check is quote with such maskedId already exists
     *
     * @param string $maskedId
     * @return bool
     */
    private function isQuoteWithSuchMaskedIdAlreadyExists(string $maskedId): bool
    {
        try {
            $this->maskedQuoteIdToQuoteId->execute($maskedId);
            return true;
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }
}

