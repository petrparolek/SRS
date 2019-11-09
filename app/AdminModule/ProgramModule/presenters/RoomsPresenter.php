<?php

declare(strict_types=1);

namespace App\AdminModule\ProgramModule\Presenters;

use App\AdminModule\ProgramModule\Components\IRoomScheduleGridControlFactory;
use App\AdminModule\ProgramModule\Components\IRoomsGridControlFactory;
use App\AdminModule\ProgramModule\Components\RoomScheduleGridControl;
use App\AdminModule\ProgramModule\Components\RoomsGridControl;
use App\Model\ACL\Permission;
use App\Model\Program\RoomRepository;
use Nette\Application\AbortException;

/**
 * Presenter obsluhující správu místností.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class RoomsPresenter extends ProgramBasePresenter
{
    /**
     * @var    RoomRepository
     * @inject
     */
    public $roomRepository;

    /**
     * @var    IRoomsGridControlFactory
     * @inject
     */
    public $roomsGridControlFactory;

    /**
     * @var    IRoomScheduleGridControlFactory
     * @inject
     */
    public $roomScheduleGridControlFactory;

    /**
     * @throws AbortException
     */
    public function startup() : void
    {
        parent::startup();

        $this->checkPermission(Permission::MANAGE_ROOMS);
    }

    public function renderDetail(int $id) : void
    {
        $room = $this->roomRepository->findById($id);

        $this->template->room = $room;
    }

    protected function createComponentRoomsGrid() : RoomsGridControl
    {
        return $this->roomsGridControlFactory->create();
    }

    protected function createComponentRoomScheduleGrid() : RoomScheduleGridControl
    {
        return $this->roomScheduleGridControlFactory->create();
    }
}
