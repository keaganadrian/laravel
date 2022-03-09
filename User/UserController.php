<?php

namespace App\Http\Controllers\User;

use App\Entities\UserEntity;
use App\Helpers\Transformer\UserTransformer;
use App\Http\Controllers\Controller;
use App\Models\Authorization\AccessRoleUser;
use App\Models\User;
use App\Repositories\User\UserRepository;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userRepository;
    public function __construct()
    {
        parent::__construct();
        $this->userRepository = new UserRepository();
    }

    public function load(){

        $userIds = AccessRoleUser::where('moet_unit_id',$this->moetUnitId)->get()->pluck('id')->toArray();
        $usersQuery = function($query) use ($userIds){
            return $query->whereIn('id',$userIds);
        };
        $users = $this->getObjectsWithConditions(new User(), $usersQuery);
        $userTransformer = new UserTransformer();
        return $this->respondSuccess([
            'users' => $users->map(function($user) use ($userTransformer){
                return $userTransformer->transform($user);
            })
        ]);
    }

    public function create(){

        $paramsKey = [
            'username',
            'password',
            'confirm_password',
            'full_name'
        ];

        if(!$this->validateParameterKeys($paramsKey) || $this->data['password'] != $this->data['confirm_password']){
            return $this->responseMissingParameters();
        }

        $existUser = (new UserEntity())->getUserInfoByUsername($this->data['username']);
        if(!is_null($existUser)){
            return $this->responseError(trans('user.exist_username'));
        }
        $roleCodes = isset($this->data['roles']) ? $this->data['roles'] : [];
        $user = $this->userRepository->createUser($this->data, $this->moetUnitId, $roleCodes);

        return $this->respondSuccess((new UserTransformer())->transform($user));
    }

    public function update(){

        $paramsKey = ['user_id'];

        if(!$this->validateParameterKeys($paramsKey) || $this->data['password'] != $this->data['confirm_password']){
            return $this->responseMissingParameters();
        }

        $user = (new UserEntity())->getUserInfoById($this->data['user_id']);
        if(is_null($user)){
            return $this->responseError(trans('user.not_found_user'));
        }

        $user = $this->userRepository->updateUserInfo($user, $this->data);
        if(isset($this->data['roles'])){
            $this->userRepository->assignUsersToMoetUnit([$user->id], $this->moetUnitId, $this->data['roles']);
        }

        return $this->respondSuccess((new UserTransformer())->transform($user));
    }

    public function delete(){
        $paramsKey = ['user_id'];

        if(!$this->validateParameterKeys($paramsKey)){
            return $this->responseMissingParameters();
        }

        $user = (new UserEntity())->getUserInfoById($this->data['user_id']);
        if(is_null($user)){
            return $this->responseError(trans('user.not_found_user'));
        }

        $this->userRepository->deleteUser($user);

        return $this->respondSuccess();
    }
}
