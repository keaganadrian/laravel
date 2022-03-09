<?php
/**
 *File name : TeacherController.php  / Date: 11/11/2021 - 4:55 PM
 *Code Owner: Dao Thi Minh Nguyet / Phone: 0985455294 / Email: nguyetdtm@omt.vn
 */

namespace App\Http\Controllers\Authorization;


use App\Entities\Authorization\AccessRoleEntity;
use App\Entities\Authorization\AuthorizationEntity;
use App\Entities\Authorization\PermissionEntity;
use App\Http\Controllers\Controller;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\JWTAuth;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;

class BaseAuthorizationController extends Controller
{
    protected $validateCreate;
    protected $validateStore;
    protected $entity;
    protected $repository;

    public function __construct()
    {
        parent::__construct();
        $this->entity               = new AuthorizationEntity();
        $this->data['moet_unit_id'] = $this->moetUnitId;
    }

    public function index()
    {
    }

    public function create()
    {
        $validator = $this->validateParameterKeys($this->validateCreate);
        if (!$validator) {
            return $this->responseMissingParameters();
        }
        return $this->respondSuccess($this->entity->getDataCreate());
    }

    public function store()
    {
        $validator = $this->validateParameterKeys($this->validateStore);
        if (!$validator) {
            return $this->responseMissingParameters();
        }
        $dataCreate = $this->repository->convertDataBeforeCreate($this->data);
        $item       = $this->repository->storeItem($dataCreate);
        $this->repository->handleAfterCreate($item, $this->data);

        return $item;
    }


    public function update()
    {
    }

    public function delete()
    {
    }

}

