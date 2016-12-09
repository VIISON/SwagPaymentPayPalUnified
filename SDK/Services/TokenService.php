<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace SwagPaymentPayPalUnified\SDK\Services;

use SwagPaymentPayPalUnified\SDK\Structs\Token;
use Shopware\Components\CacheManager;

class TokenService
{
    const CACHE_ID = 'paypal_unified_auth';

    /** @var CacheManager $cacheManager */
    private $cacheManager;

    /**
     * @param CacheManager $cacheManager
     */
    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param Token $token
     */
    public function setToken(Token $token)
    {
        $this->cacheManager->getCoreCache()->save(serialize($token), self::CACHE_ID);
    }

    /**
     * @return Token
     */
    public function getCachedToken()
    {
        return unserialize($this->cacheManager->getCoreCache()->load(self::CACHE_ID));
    }

    public function removeToken()
    {
        $this->cacheManager->getCoreCache()->remove(self::CACHE_ID);
    }

    /**
     * @param Token $token
     * @return bool
     */
    public function isValid(Token $token)
    {
        $dateTimeNow = new \DateTime();
        $dateTimeExpire = $token->getExpireDateTime();
        //Decrease expire date by one hour just to make sure, we don't run into an unauthorized exception.
        $dateTimeExpire = $dateTimeExpire->sub(new \DateInterval('PT1H'));

        if ($dateTimeExpire < $dateTimeNow) {
            return false;
        }

        return true;
    }
}
