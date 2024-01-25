<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Atama\Share\Model\Resolver;

use Atama\Share\Model\Resolver\DataProvider\Atamashare;
use Magento\Checkout\Model\Session;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CreateSessionGuestCart implements ResolverInterface
{

    /**
     * @var Atamashare
     */
    private $atamashareDataProvider;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @param Session $session
     * @codeCoverageIgnore
     */
    public function __construct(
        Atamashare $atamashareDataProvider,
        Session $session,
    ) {
        $this->session = $session;
        $this->atamashareDataProvider = $atamashareDataProvider;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        error_log("OK!!!");
        error_log($this->session->getSessionId());
        return $this->atamashareDataProvider->getAtamashare($this->session->getSessionId());
    }
}

