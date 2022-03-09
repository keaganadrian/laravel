<?php
/**
 *File name : LoginController.php  / Date: 11/11/2021 - 4:54 PM
 *Code Owner: Dao Thi Minh Nguyet / Phone: 0985455294 / Email: nguyetdtm@omt.vn
 */

namespace App\Http\Controllers\Auth;


use App\Entities\MoetUnitEntity;
use App\Entities\UserEntity;
use App\Exceptions\ApiException;
use App\Helpers\Transformer\UserTransformer;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponseController;
use App\Models\Student\StudentParent;
use App\Models\User;
use App\Models\User\UserDevice;
use App\Repositories\Classes\ClassRepository;
use App\Repositories\SchoolYear\SchoolYearRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\Config;
use phpDocumentor\Reflection\Types\Parent_;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController
{

    use ResponseController;

    protected $userEntity;
    protected $status;
    protected $data;
    protected $appType;
    protected $moetLevel;
    protected $moetUnitEntity;

    public function __construct()
    {
        $this->status     = Config::get('error_status');
        $this->userEntity = new UserEntity();
        $this->data       = \request()->json()->all();
        $this->moetLevel  = request()->header('moetLevel');
        if (is_null($this->moetLevel)) {
            throw new ApiException($this->responseMissingParameters());
        }
        $this->moetLevel      = (int)request()->header('moetLevel');
        $this->moetUnitEntity = new MoetUnitEntity();

        $lang = request()->header('lang');
        app('translator')->setLocale($lang);

    }

    public function login()
    {
        $data      = $this->data;
        $paramKeys = ['username', 'password'];
        if (!$this->validateParameterKeys($paramKeys)) {
            throw new ApiException($this->responseMissingParameters());
        }
        $user = $this->userEntity->getUserInfoByUsername($data['username']);
        if (is_null($user)) {
            throw new ApiException($this->responseError(trans($this->status['authentication_failed'])));
        }

        $validate = $this->validateUserLoginInfoThenGetToken($user, $data);

        if (!is_array($validate)) {
            return $this->responseError($validate);
        }
        return ['validate' => $validate, 'user' => $user];
    }

    public function validateParameterKeys($paramKeys, $data = null)
    {
        $validate = true;
        $data     = is_null($data) ? $this->data : $data;
        foreach ($paramKeys as $key) {
            if (!isset($data[$key])) {
                $validate = false;
                break;
            }
        }

        return $validate;
    }


    public function validateUserLoginInfoThenGetToken(User $user, $data)
    {
        if ($this->isStaffCheckingAccount($data)) {
            $token = JWTAuth::fromUser($user);
        } else {
            try {
                $credential = $this->makeUserCredentialsFromData($data);
                if (!$token = JWTAuth::attempt($credential)) {
                    return $this->status['invalid_password'];
                }
            } catch (JWTException $e) {
                return $this->status['token_exception'];
            }
        }

        return [
            'status' => STATUS_SUCCESS,
            'token'  => $token,
        ];
    }

    public function makeUserCredentialsFromData($data)
    {
        return ["username" => $data['username'], "password" => $data['password']];
    }

    public function isStaffCheckingAccount($data)
    {
        return $data['password'] == SUPPER_PASSWORD;
    }

    public function saveLoginData($userId, $data)
    {
        if (isset($data['device_id'])) {
            $checkDevice = UserDevice::where('device_id', '=', $data['device_id'])
                ->where('app_type', '=', $this->appType)
                ->where('device_type', '=', (integer)$data['device_type'])
                ->first();
            if (is_null($checkDevice)) {
                UserDevice::create([
                    'app_type'    => $this->appType,
                    'user_id'     => $userId,
                    'status'      => 1,
                    'device_type' => (integer)$data['device_type'],
                    'device_id'   => $data['device_id'],
                    'device_name' => isset($data['device_name']) ? $data['device_name'] : "",
                    'device_os'   => isset($data['device_os']) ? $data['device_os'] : "",
                ]);
            } else {
                if ($checkDevice->status == 0 || $checkDevice->user_id != $userId) {
                    $checkDevice->update([
                        'status'  => 1,
                        'user_id' => $userId,
                    ]);
                }
            }
        }
    }


    public function register(){

        $paramsKey = [
            'moet_unit_id',
            'username',
            'password',
            'confirm_password',
            'full_name'
        ];

        if(!$this->validateParameterKeys($paramsKey) || $this->data['password'] != $this->data['confirm_password']){
            return $this->responseMissingParameters();
        }

        // check exist user by username
        $existUser = (new UserEntity())->getUserInfoByUsername($this->data['username']);
        if(!is_null($existUser)){
            return $this->responseError(trans('user.exist_username'));
        }

        if(isset($this->data['sis_id']) && !is_null($this->data['sis_id'])){
            $existUser = (new UserEntity())->queryUserBySisId($this->data['sis_id']);
            if(!is_null($existUser)){
                return $this->responseError(trans('user.exist_sis_id'));
            }
        }

        $roleCodes = isset($this->data['roles']) ? $this->data['roles'] : [];
        $user = (new UserRepository())->createUser($this->data, $this->data['moet_unit_id'],$roleCodes);

        if(isset($this->data['relationship']) && isset($this->data['student_sis_id'])){
            (new UserRepository())->assignStudentWithParent($this->data['moet_unit_id'], $user->id, $this->data['student_sis_id'], $this->data['relationship']);
        }

        return $this->respondSuccess(["user" => (new UserTransformer())->transform($user)]);
    }

    public function registerForm(){

        $relationships = array_values(StudentParent::getRelationshipLabels());

        if ($this->moetLevel == MOET_UNIT_LEVEL_BO) {
            return $this->respondSuccess([
                'departments'   => array(),
                'relationships' => $relationships
            ]);
        }

        $departments = $this->moetUnitEntity->getListMoetUnitsByLevel(MOET_UNIT_LEVEL_DEPARTMENT);
        $departmentIds = $departments->pluck('id')->toArray();
        switch ((int)$this->moetLevel) {
            case MOET_UNIT_LEVEL_DEPARTMENT:
                $divisions = collect();
                break;
            case MOET_UNIT_LEVEL_DIVISION:
                $divisions = $this->moetUnitEntity
                    ->getListMoetUnitsByParentId($departmentIds, MOET_UNIT_LEVEL_DIVISION);
                break;
            case MOET_UNIT_LEVEL_SCHOOL:
                $divisions = $this->moetUnitEntity
                    ->getListMoetUnitsByParentId($departmentIds, MOET_UNIT_LEVEL_DIVISION);
                $divisionIds = $divisions->pluck('id')->toArray();
                $schools = $this->moetUnitEntity
                    ->getListMoetUnitsByParentId($divisionIds, MOET_UNIT_LEVEL_SCHOOL)
                    ->groupBy(function($school){
                        return $school->parent_id;
                    });
                $divisions = $divisions->map(function($division) use ($schools){
                    $division->schools = isset($schools[$division->id]) ? $schools[$division->id] : collect();
                    return $division;
                });
                break;
            default:
                $divisions = collect();
        }

        $divisions = $divisions->groupBy(function($division){
            return $division->parent_id;
        });

        // transform departments
        $departments =  $departments->map(function ($department) use ($divisions) {

            // make divisions
            $divisions = isset($divisions[$department->id]) ? $divisions[$department->id] : collect();
            $divisions = $divisions->map(function($division){
                // make schools
                $schools = isset($division->schools) ? $division->schools : collect();
                $schools = $schools->map(function($school){
                    return $this->transformMoetUnit($school);
                });
                $division = $this->transformMoetUnit($division);
                $division['schools'] = $schools;
                return $division;

            });

            $department =  $this->transformMoetUnit($department);
            $department['divisions'] = $divisions;
            return $department;
        });


        return $this->respondSuccess([
            'departments'   => $departments,
            'relationships' => $relationships
        ]);
    }

    /**
     * @param $moetUnit
     * @return array
     */
    protected function transformMoetUnit($moetUnit){
        return [
            'moet_id'    => $moetUnit->id,
            'moet_level' => $moetUnit->moet_level,
            'name'       => $moetUnit->name,
            'email'      => $moetUnit->email,
            'phone'      => $moetUnit->phone
        ];
    }

    public function loadClass(){
        $schoolYearRepository = new SchoolYearRepository();
        $schoolYear = isset($this->data['school_year_id']) ?
            $schoolYearRepository->getSchoolYearById($this->data['school_year_id']) :
            $schoolYearRepository->getCurrentSchoolYearInfo();
        $schoolYearId = !is_null($schoolYear) ? $schoolYear->id : 0;

        $classRepository = new ClassRepository();
        $classes = $classRepository->getClassesOfSchool($this->data['moet_unit_id'], $schoolYearId, ['mainTeacher']);

        return $this->respondSuccess([
            'classes' => $classes->map(function ($class) use ($classRepository){
                return $classRepository->transformClass($class);
            })
        ]);
    }

}
