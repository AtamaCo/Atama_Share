<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Atama\Share\Model\Resolver\DataProvider;

class Atamashare
{

    public function __construct()
    {

    }

    public function getAtamashare(string $thing): array
    {
        return [
            "id" => "cool {$thing}"
        ];
    }
}

