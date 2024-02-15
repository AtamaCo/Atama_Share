<?php

namespace Atama\Share\Plugin\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Api\Exception\UserTokenException;
use Magento\Integration\Model\CustomUserContext;
use Magento\Integration\Model\UserToken\UserTokenParametersFactory;
use Magento\Integration\Api\UserTokenRevokerInterface;

class Logout
{

    /**
     * @var UserTokenRevokerInterface
     */
    protected $tokenRevoker;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * Initialize service
     *
     * @param UserTokenRevokerInterface|null $tokenRevoker
     */
    public function __construct(
        Session $customerSession,
        ?UserTokenRevokerInterface $tokenRevoker = null
    ) {
        $this->customerSession = $customerSession;
        $this->tokenRevoker = $tokenRevoker ?? ObjectManager::getInstance()->get(UserTokenRevokerInterface::class);
    }

    public function aroundExecute(\Magento\Customer\Controller\Account\Logout $subject, \Closure $proceed)
    {

        $customerId = $this->customerSession->getCustomerId();
        $this->tokenRevoker->revokeFor(new CustomUserContext((int)$customerId, CustomUserContext::USER_TYPE_CUSTOMER));

        $result = $proceed();

        return $result;
    }

    /**
     * Revoke token by customer id.
     *
     * @param int $customerId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function revokeCustomerAccessToken($customerId)
    {
        try {
            $this->tokenRevoker->revokeFor(new CustomUserContext((int)$customerId, CustomUserContext::USER_TYPE_CUSTOMER));
        } catch (UserTokenException $exception) {
            throw new LocalizedException(__('Failed to revoke customer\'s access tokens'), $exception);
        }
        return true;
    }
}
?>
