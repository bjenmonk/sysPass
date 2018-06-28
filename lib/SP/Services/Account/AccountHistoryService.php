<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
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

namespace SP\Services\Account;

use SP\Core\Exceptions\QueryException;
use SP\Core\Exceptions\SPException;
use SP\DataModel\AccountHistoryData;
use SP\DataModel\ItemData;
use SP\DataModel\ItemSearchData;
use SP\Repositories\Account\AccountHistoryRepository;
use SP\Repositories\Account\AccountToUserGroupRepository;
use SP\Repositories\Account\AccountToUserRepository;
use SP\Repositories\NoSuchItemException;
use SP\Services\Service;
use SP\Services\ServiceException;
use SP\Storage\Database\QueryResult;

/**
 * Class AccountHistoryService
 *
 * @package SP\Services\Account
 */
class AccountHistoryService extends Service
{
    /**
     * @var AccountHistoryRepository
     */
    protected $accountHistoryRepository;
    /**
     * @var AccountToUserGroupRepository
     */
    protected $accountToUserGroupRepository;
    /**
     * @var AccountToUserRepository
     */
    protected $accountToUserRepository;

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function initialize()
    {
        $this->accountHistoryRepository = $this->dic->get(AccountHistoryRepository::class);
        $this->accountToUserRepository = $this->dic->get(AccountToUserRepository::class);
        $this->accountToUserGroupRepository = $this->dic->get(AccountToUserGroupRepository::class);
    }

    /**
     * Returns the item for given id
     *
     * @param int $id
     *
     * @return AccountHistoryData
     * @throws SPException
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getById($id)
    {
        $results = $this->accountHistoryRepository->getById($id);

        if ($results->getNumRows() === 0) {
            throw new NoSuchItemException(__u('No se pudieron obtener los datos de la cuenta'));
        }

        return $results->getData();
    }

    /**
     * Obtiene el listado del histórico de una cuenta.
     *
     * @param $id
     *
     * @return array Con los registros con id como clave y fecha - usuario como valor
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function getHistoryForAccount($id)
    {
        return self::mapHistoryForDateSelect($this->accountHistoryRepository->getHistoryForAccount($id)->getDataAsArray());
    }

    /**
     * Masps history items to fill in a date select
     *
     * @param array $history
     *
     * @return array
     */
    private static function mapHistoryForDateSelect(array $history)
    {
        $items = [];

        foreach ($history as $item) {
            // Comprobamos si la entrada en el historial es la primera (no tiene editor ni fecha de edición)
            if (empty($item->dateEdit) || $item->dateEdit === '0000-00-00 00:00:00') {
                $date = $item->dateAdd . ' - ' . $item->userAdd;
            } else {
                $date = $item->dateEdit . ' - ' . $item->userEdit;
            }

            $items[$item->id] = $date;
        };

        return $items;
    }

    /**
     * @param $id
     *
     * @return ItemData[]
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function getUsersByAccountId($id)
    {
        return $this->accountToUserRepository->getUsersByAccountId($id);
    }

    /**
     * @param $id
     *
     * @return ItemData[]
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function getUserGroupsByAccountId($id)
    {
        return $this->accountToUserGroupRepository->getUserGroupsByAccountId($id);
    }

    /**
     * @param ItemSearchData $itemSearchData
     *
     * @return QueryResult
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function search(ItemSearchData $itemSearchData)
    {
        return $this->accountHistoryRepository->search($itemSearchData);
    }

    /**
     * Crea una nueva cuenta en la BBDD
     *
     * @param array $itemData ['id' => <int>, 'isModify' => <bool>,'isDelete' => <bool>, 'masterPassHash' => <string>]
     *
     * @return bool
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function create($itemData)
    {
        return $this->accountHistoryRepository->create($itemData);
    }

    /**
     * @return array
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function getAccountsPassData()
    {
        return $this->accountHistoryRepository->getAccountsPassData()->getDataAsArray();
    }

    /**
     * Elimina los datos de una cuenta en la BBDD.
     *
     * @param array|int $id
     *
     * @throws QueryException
     * @throws ServiceException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function delete($id)
    {
        if ($this->accountHistoryRepository->delete($id) === 0) {
            throw new ServiceException(__u('Error al eliminar la cuenta'));
        }
    }

    /**
     * Deletes all the items for given ids
     *
     * @param array $ids
     *
     * @return int
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function deleteByIdBatch(array $ids)
    {
        return $this->accountHistoryRepository->deleteByIdBatch($ids);
    }

    /**
     * @param AccountPasswordRequest $accountRequest
     *
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function updatePasswordMasterPass(AccountPasswordRequest $accountRequest)
    {
        if ($this->accountHistoryRepository->updatePassword($accountRequest) !== 1) {
            throw new ServiceException(__u('Error al actualizar la clave'));
        }
    }

    /**
     * Returns all the items
     *
     * @return array
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function getAll()
    {
        return self::mapHistoryForDateSelect($this->accountHistoryRepository->getAll()->getDataAsArray());
    }
}