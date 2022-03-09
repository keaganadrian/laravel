<?php
/**
 *File name : LoginController.php  / Date: 11/11/2021 - 4:54 PM
 *Code Owner: Dao Thi Minh Nguyet / Phone: 0985455294 / Email: nguyetdtm@omt.vn
 */

namespace App\Http\Controllers\Auth\App;


use App\Entities\UserEntity;
use App\Helpers\Transformer\UserTransformer;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Auth;

class LoginController extends AuthController
{


    protected $userEntity;
    protected $userTransformer;
    protected $appType = APP_TYPE_STUDENT;

    public function __construct()
    {
        parent::__construct();
        $this->userEntity      = new UserEntity();
        $this->userTransformer = new UserTransformer();
    }

    public function login()
    {
        $dataLogin = parent::login();
        $this->saveLoginData($dataLogin['user']->id, $this->data);

        return $this->respondSuccess([
            'token' => $dataLogin['validate']['token'],
            'user'  => $this->userTransformer->transform($dataLogin['user']->toArray()),
        ]);
    }

    public function logout()
    {
        Auth::logout();
        return $this->respondSuccess();
    }

}
