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
class PremioPoints implements SectionSourceInterface
{

    /**
     * @var CurrentCustomer
     */
    private $currentCustomer;




    /**

     * @param CurrentCustomer $currentCustomer

     */
    public function __construct(
        CurrentCustomer $currentCustomer,
    ) {

        $this->currentCustomer = $currentCustomer;

    }

    /**
     * @inheritdoc
     */
    public function getSectionData(): array
    {
        if (null === $this->currentCustomer->getCustomerId()) {
            return [
                "isLoggedIn" => false,
                "isPremioEnabled" => true
            ];
        }

        return [
            "isLoggedIn" => true,
            "isPremioEnabled" => true,
            "pointsBalance" => random_int(0, 500)
        ];

    }

}
