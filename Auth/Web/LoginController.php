<?php
/**
 *File name : LoginController.php  / Date: 11/11/2021 - 4:54 PM
 *Code Owner: Dao Thi Minh Nguyet / Phone: 0985455294 / Email: nguyetdtm@omt.vn
 */

namespace App\Http\Controllers\Auth\Web;


use App\Entities\UserEntity;
use App\Exceptions\ApiException;
use App\Helpers\Transformer\UserTransformer;
use App\Http\Controllers\Auth\AuthController;
use App\Models\Student\Student;
use App\Models\Teacher\Teacher;
use Illuminate\Support\Facades\Auth;

class LoginController extends AuthController
{


    protected $userEntity;
    protected $userTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->userEntity = new UserEntity();
        $this->userTransformer = new UserTransformer();
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginForm()
    {

        if ($this->moetLevel === MOET_UNIT_LEVEL_BO) {
            return $this->respondSuccess([
                'departments' => array()
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
                    ->groupBy(function ($school) {
                        return $school->parent_id;
                    });
                $divisions = $divisions->map(function ($division) use ($schools) {
                    $division->schools = isset($schools[$division->id]) ? $schools[$division->id] : collect();

                    return $division;
                });
                break;
            default:
                $divisions = collect();
        }

        $divisions = $divisions->groupBy(function ($division) {
            return $division->parent_id;
        });

        // transform departments
        $departments = $departments->map(function ($department) use ($divisions) {

            // make divisions
            $divisions = isset($divisions[$department->id]) ? $divisions[$department->id] : collect();
            $divisions = $divisions->map(function ($division) {
                // make schools
                $schools = isset($division->schools) ? $division->schools : collect();
                $schools = $schools->map(function ($school) {
                    return $this->transformMoetUnit($school);
                });
                $division = $this->transformMoetUnit($division);
                $division['schools'] = $schools;

                return $division;

            });

            $department = $this->transformMoetUnit($department);
            $department['divisions'] = $divisions;

            return $department;
        });


        return $this->respondSuccess([
            'departments' => $departments
        ]);
    }

    /**
     * @param $moetUnit
     * @return array
     */
    protected function transformMoetUnit($moetUnit)
    {
        return [
            'moet_id'    => $moetUnit->id,
            'moet_level' => $moetUnit->moet_level,
            'name'       => $moetUnit->name,
            'email'      => $moetUnit->email,
            'phone'      => $moetUnit->phone
        ];
    }

    public function login()
    {
        $dataLogin = parent::login();
        if(!is_array($dataLogin)){
            return $dataLogin;
        }

        $moetUnitIds = $dataLogin['user']->roles->pluck('moet_unit_id')->toArray();
        $moetUnits = $this->moetUnitEntity->getListMoetUnitByIds($moetUnitIds);

        if (!$moetUnits->contains('moet_level', $this->moetLevel)) {
            throw new ApiException($this->responseError(trans($this->status['do_not_have_permission_unit'])));
        }
        if (isset($this->data['moetUnitId'])) {
            if (!$moetUnits->contains('id', $this->data['moetUnitId'])) {
                throw new ApiException($this->responseError(trans($this->status['do_not_have_permission_unit'])));
            }
        }

        $result = [
            'token' => $dataLogin['validate']['token'],
            'user'  => $this->userTransformer->transform($dataLogin['user']),
        ];
        if ($this->moetLevel == MOET_UNIT_LEVEL_SCHOOL) {
            $result['user'] = array_merge($result['user'], $this->checkClassOfTeacherOrStudent($dataLogin['user']['id'],
                $moetUnits->pluck('id')->toArray()));
        }

        return $this->respondSuccess($result);
    }

    private function checkClassOfTeacherOrStudent($userId, $schoolIds)
    {
        $resutl = [];

        // get class teacher
        $classTeacher = Teacher::where('user_id', $userId)
            ->whereIn('school_id', $schoolIds)
            ->where('status', 1)
            ->with('thisClass')
            ->orderBy('created_at', 'DESC')
            ->get()->last();
        if (!is_null($classTeacher) && !is_null($classTeacher->thisClass)) {
            $resutl['teacher_class_id'] = $classTeacher->class_id;
            $resutl['teacher_class_name'] = $classTeacher->thisClass->name;
        }

        // get class student
        $classStudent = Student::where('user_id', $userId)
            ->whereIn('school_id', $schoolIds)
            ->where('status', 1)
            ->with('thisClass')
            ->orderBy('created_at', 'DESC')
            ->get()->last();
        if (!is_null($classStudent) && !is_null($classStudent->thisClass)) {
            $resutl['student_class_id'] = $classStudent->class_id;
            $resutl['student_class_name'] = $classStudent->thisClass->name;
        }

        return $resutl;
    }

    public function logout()
    {
        Auth::logout();

        return $this->respondSuccess();
    }

}
