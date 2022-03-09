<?php
/**
 *File name : TeacherController.php  / Date: 11/11/2021 - 4:55 PM
 *Code Owner: Dao Thi Minh Nguyet / Phone: 0985455294 / Email: nguyetdtm@omt.vn
 */

namespace App\Http\Controllers\Authorization;


use App\Entities\Authorization\AccessRoleEntity;
use App\Entities\Authorization\PermissionEntity;
use App\Http\Controllers\Controller;
use App\Repositories\Authorization\AccessRoleRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\JWTAuth;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;

class AccessRoleController extends BaseAuthorizationController
{

    protected $entity;
    protected $validateCreate;
    protected $validateStore;

    public function __construct()
    {
        parent::__construct();
        $this->entity         = new AccessRoleEntity($this->moetUnit);
        $this->validateCreate = ['role_id'];
        $this->validateStore  = ['name', 'code'];
        $this->repository     = new AccessRoleRepository();
    }

    public function index()
    {
        return $this->respondSuccess($this->entity->getRolesOfMoetUnit());
    }

    public function users()
    {
        if (!$this->validateParameterKeys(['role_id'])) {
            return $this->responseMissingParameters();
        }
        return $this->respondSuccess($this->entity->getUserOfRole($this->data['role_id']));
    }

    public function getUsersUnassignedRole()
    {
        if (!$this->validateParameterKeys(['role_id'])) {
            return $this->responseMissingParameters();
        }
        return $this->respondSuccess($this->entity->getUsersUnassignedRole($this->data['role_id']));
    }


    public function assignUserRole()
    {
        if (!$this->validateParameterKeys(['role_id', 'user_ids'])) {
            return $this->responseMissingParameters();
        }
        $role           = $this->entity->getAccessRoleInfo($this->data['role_id']);
        $userRepository = new UserRepository();
        $userRepository->assignUsersToMoetUnit($this->data['user_ids'], $this->moetUnitId, $role->code);
        return $this->respondSuccess();
    }

}

