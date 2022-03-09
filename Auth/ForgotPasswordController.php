<?php

namespace App\Http\Controllers\Auth;

use App\Entities\UserEntity;
use App\Helpers\Transformer\UserTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\User\ForgotPasswordRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Http\Request;

class ForgotPasswordController extends AuthController
{
    protected $forgotPasswordRepository;

    public function __construct()
    {
        parent::__construct();
        $this->forgotPasswordRepository = new ForgotPasswordRepository();
    }

    public function sendOTP(){

        $paramsKey = ['username'];

        if(!$this->validateParameterKeys($paramsKey)){
            return $this->responseMissingParameters();
        }

        // check exist user by username
        $user = (new UserEntity())->getUserInfoByUsername($this->data['username']);
        if(is_null($user)){
            return $this->responseError(trans('user.not_found_user'));
        }

        // send OTP to email and phone number
        $sendOTP = $this->forgotPasswordRepository->sendOTPToUser($user);
        if(!$sendOTP){
            return $this->responseError(trans('user.send_OTP_unsuccessfully'));
        }

        return $this->respondSuccess([
            'username' => $user->username,
            'message'  => trans('user.send_OTP_successfully')
        ]);

    }

    public function resetPassword(){
        $paramsKey = ['username','otp','password','confirm_password'];
        if(!$this->validateParameterKeys($paramsKey) || $this->data['password'] != $this->data['confirm_password']){
            return $this->responseMissingParameters();
        }

        // check exist user by username
        $user = (new UserEntity())->getUserInfoByUsername($this->data['username']);
        if(is_null($user)){
            return $this->responseError(trans('user.not_found_user'));
        }

        // check otp
        $otp = $this->forgotPasswordRepository->getOtpByCode($this->data['otp'], $user->id);
        if(is_null($otp)){
            return $this->responseError(trans('user.otp_is_not_correct'));
        }
        if($otp->isExpired()){
            return $this->responseError(trans('user.otp_is_expired'));
        }

        // update password
        $password = $this->data['password'];
        (new UserRepository())->updateUserInfo($user, ['password' => $password]);

        return $this->respondSuccess(['message' => trans('user.reset_password_successfully')]);
    }
}
