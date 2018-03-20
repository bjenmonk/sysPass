<?php
/**
 * sysPass
 *
 * @author nuxsmin
 * @link https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Services\AuthToken;

use SP\Core\Acl\Acl;
use SP\Core\Acl\ActionsInterface;
use SP\Core\Crypt\Hash;
use SP\Core\Crypt\Session as CryptSession;
use SP\Core\Crypt\Vault;
use SP\Core\Exceptions\SPException;
use SP\DataModel\AuthTokenData;
use SP\DataModel\ItemSearchData;
use SP\Repositories\AuthToken\AuthTokenRepository;
use SP\Services\Service;
use SP\Services\ServiceException;
use SP\Services\ServiceItemTrait;
use SP\Util\Util;

/**
 * Class AuthTokenService
 *
 * @package SP\Services\AuthToken
 */
class AuthTokenService extends Service
{
    use ServiceItemTrait;

    /**
     * @var AuthTokenRepository
     */
    protected $authTokenRepository;

    /**
     * @param ItemSearchData $itemSearchData
     * @return mixed
     */
    public function search(ItemSearchData $itemSearchData)
    {
        return $this->authTokenRepository->search($itemSearchData);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getById($id)
    {
        return $this->authTokenRepository->getById($id);
    }

    /**
     * @param $id
     * @return AuthTokenService
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function delete($id)
    {
        if ($this->authTokenRepository->delete($id) === 0) {
            throw new SPException(__u('Token no encontrado'), SPException::INFO);
        }

        return $this;
    }

    /**
     * Deletes all the items for given ids
     *
     * @param array $ids
     * @return bool
     * @throws ServiceException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function deleteByIdBatch(array $ids)
    {
        if (($count = $this->authTokenRepository->deleteByIdBatch($ids)) !== count($ids)) {
            throw new ServiceException(__u('Error al eliminar tokens'), ServiceException::WARNING);
        }

        return $count;
    }

    /**
     * @param $itemData
     * @return mixed
     * @throws SPException
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function create($itemData)
    {
        $this->injectSecureData($itemData);

        return $this->authTokenRepository->create($itemData);
    }

    /**
     * Injects secure data for token
     *
     * @param AuthTokenData $authTokenData
     * @param  string $token
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    private function injectSecureData(AuthTokenData $authTokenData, $token = null)
    {
        if ($token === null) {
            $token = $this->authTokenRepository->getTokenByUserId($authTokenData->getUserId()) ?: $this->generateToken();
        }

        $action = $authTokenData->getActionId();

        if ($action === ActionsInterface::ACCOUNT_VIEW_PASS
            || $action === ActionsInterface::ACCOUNT_CREATE
        ) {
            $authTokenData->setVault($this->getSecureData($token, $authTokenData->getHash()));
            $authTokenData->setHash(Hash::hashKey($authTokenData->getHash()));
        } else {
            $authTokenData->setHash(null);
        }

        $authTokenData->setToken($token);
        $authTokenData->setCreatedBy($this->context->getUserData()->getId());
    }

    /**
     * Generar un token de acceso
     *
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    private function generateToken()
    {
        return Util::generateRandomBytes(32);
    }

    /**
     * Generar la llave segura del token
     *
     * @param string $token
     * @param string $hash
     * @return Vault
     * @throws \Defuse\Crypto\Exception\CryptoException
     */
    private function getSecureData($token, $hash)
    {
        return (new Vault())
            ->saveData(CryptSession::getSessionKey($this->context), $hash . $token);
    }

    /**
     * @param AuthTokenData $itemData
     * @return mixed
     * @throws SPException
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function refreshAndUpdate(AuthTokenData $itemData)
    {
        $token = $this->generateToken();
        $vault = serialize($this->getSecureData($token, $itemData->getHash()));

        $this->authTokenRepository->refreshTokenByUserId($itemData->getUserId(), $token);
        $this->authTokenRepository->refreshVaultByUserId($itemData->getUserId(), $vault, Hash::hashKey($itemData->getHash()));

        return $this->update($itemData, $token);
    }

    /**
     * @param AuthTokenData $itemData
     * @param string $token
     * @return mixed
     * @throws SPException
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function update(AuthTokenData $itemData, $token = null)
    {
        $this->injectSecureData($itemData, $token);

        return $this->authTokenRepository->update($itemData);
    }

    /**
     * Devolver los datos de un token
     *
     * @param $actionId int El id de la accion
     * @param $token    string El token de seguridad
     * @return false|AuthTokenData
     */
    public function getTokenByToken($actionId, $token)
    {
        return $this->authTokenRepository->getTokenByToken($actionId, $token);
    }

    /**
     * @return array
     */
    public function getAllBasic()
    {
        return $this->authTokenRepository->getAll();
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initialize()
    {
        $this->authTokenRepository = $this->dic->get(AuthTokenRepository::class);
    }

    /**
     * Devuelver un array de acciones posibles para los tokens
     *
     * @return array
     */
    public static function getTokenActions()
    {
        $actions = [
            ActionsInterface::ACCOUNT_SEARCH => Acl::getActionInfo(ActionsInterface::ACCOUNT_SEARCH),
            ActionsInterface::ACCOUNT_VIEW => Acl::getActionInfo(ActionsInterface::ACCOUNT_VIEW),
            ActionsInterface::ACCOUNT_VIEW_PASS => Acl::getActionInfo(ActionsInterface::ACCOUNT_VIEW_PASS),
            ActionsInterface::ACCOUNT_DELETE => Acl::getActionInfo(ActionsInterface::ACCOUNT_DELETE),
            ActionsInterface::ACCOUNT_CREATE => Acl::getActionInfo(ActionsInterface::ACCOUNT_CREATE),
            ActionsInterface::BACKUP_CONFIG => Acl::getActionInfo(ActionsInterface::BACKUP_CONFIG),
            ActionsInterface::CATEGORY_SEARCH => Acl::getActionInfo(ActionsInterface::CATEGORY_SEARCH),
            ActionsInterface::CATEGORY_VIEW => Acl::getActionInfo(ActionsInterface::CATEGORY_VIEW),
            ActionsInterface::CATEGORY_CREATE => Acl::getActionInfo(ActionsInterface::CATEGORY_CREATE),
            ActionsInterface::CATEGORY_EDIT => Acl::getActionInfo(ActionsInterface::CATEGORY_EDIT),
            ActionsInterface::CATEGORY_DELETE => Acl::getActionInfo(ActionsInterface::CATEGORY_DELETE),
            ActionsInterface::CLIENT_SEARCH => Acl::getActionInfo(ActionsInterface::CLIENT_SEARCH),
            ActionsInterface::CLIENT_VIEW => Acl::getActionInfo(ActionsInterface::CLIENT_VIEW),
            ActionsInterface::CLIENT_CREATE => Acl::getActionInfo(ActionsInterface::CLIENT_CREATE),
            ActionsInterface::CLIENT_EDIT => Acl::getActionInfo(ActionsInterface::CLIENT_EDIT),
            ActionsInterface::CLIENT_DELETE => Acl::getActionInfo(ActionsInterface::CLIENT_DELETE),
            ActionsInterface::TAG_SEARCH => Acl::getActionInfo(ActionsInterface::TAG_SEARCH),
            ActionsInterface::TAG_VIEW => Acl::getActionInfo(ActionsInterface::TAG_VIEW),
            ActionsInterface::TAG_CREATE => Acl::getActionInfo(ActionsInterface::TAG_CREATE),
            ActionsInterface::TAG_EDIT => Acl::getActionInfo(ActionsInterface::TAG_EDIT),
            ActionsInterface::TAG_DELETE => Acl::getActionInfo(ActionsInterface::TAG_DELETE),
        ];

        return $actions;
    }
}