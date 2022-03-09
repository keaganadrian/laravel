<?php

namespace App\Http\Controllers\Classes;

use App\Helpers\Transformer\UserTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\Classes\ClassRepository;
use App\Repositories\Grade\GradeRepository;
use App\Repositories\SchoolYear\SchoolYearRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Http\Request;

class ClassController extends Controller
{

    protected $classRepository;

    public function __construct()
    {
        parent::__construct();
        $this->classRepository = new ClassRepository();
    }

    public function load()
    {
        $schoolYearRepository = new SchoolYearRepository();
        $schoolYear = isset($this->data['school_year_id']) ?
            $schoolYearRepository->getSchoolYearById($this->data['school_year_id']) :
            $schoolYearRepository->getCurrentSchoolYearInfo();
        $schoolYearId = !is_null($schoolYear) ? $schoolYear->id : 0;

        $classes = $this->classRepository->getClassesOfSchool($this->moetUnitId, $schoolYearId, ['mainTeacher']);

        return $this->respondSuccess([
            'classes' => $classes->map(function ($class) {
                return $this->classRepository->transformClass($class);
            })
        ]);
    }

    public function create()
    {
        $paramsKey = [
            'name',
            'grade_id'
        ];

        if (!$this->validateParameterKeys($paramsKey)) {
            return $this->responseMissingParameters();
        }

        if (!isset($this->data['school_year_id'])) {
            $schoolYear = (new SchoolYearRepository())->getCurrentSchoolYearInfo();
            $this->data['school_year_id'] = !is_null($schoolYear) ? $schoolYear->id : 0;
        }

        $class = $this->classRepository->createsssClass($this->moetUnitId, $this->data);

        return $this->respondSuccess([
            'class' => $this->classRepository->transformClass($class)
        ]);
    }

    public function update()
    {
        $paramsKey = [
            'class_id'
        ];

        if (!$this->validateParameterKeys($paramsKey)) {
            return $this->responseMissingParameters();
        }

        $class = $this->classRepository->getClassInfo($this->data['class_id'], $this->moetUnitId, ['mainTeacher']);
        if (is_null($class)) {
            return $this->responseNotFoundClass();
        }

        $class = $this->classRepository->updateClass($class, $this->data);

        return $this->respondSuccess([
            'class' => $this->classRepository->transformClass($class)
        ]);
    }

    public function delete()
    {
        $paramsKey = [
            'class_id'
        ];

        if (!$this->validateParameterKeys($paramsKey)) {
            return $this->responseMissingParameters();
        }

        $class = $this->classRepository->getClassInfo($this->data['class_id'], $this->moetUnitId, ['mainTeacher']);
        if (is_null($class)) {
            return $this->responseNotFoundClass();
        }

        $this->classRepository->deleteClass($class);

        return $this->respondSuccess();

    }

    public function getMainTeachers()
    {
        $userRepository = new UserRepository();
        $teachers = $userRepository->getUsersFromMoetUnit($this->moetUnit, ['GV']);
        $userTransformer = new UserTransformer();

        return $this->respondSuccess([
            'teachers' => $teachers->map(function ($teacher) use ($userTransformer) {
                return $userTransformer->transform($teacher);
            })->values()
        ]);
    }

    public function getGrades()
    {
        $grades = (new GradeRepository())->getAllGrades();

        return $this->respondSuccess([
            'grades' => $grades->map(function ($grade) {
                return [
                    'id'     => (integer)$grade->id,
                    'name'   => $grade->name,
                    'code'   => $grade->code,
                    'stage'  => (integer)$grade->educational_stages,
                    'offset' => (integer)$grade->index_order
                ];
            })->values()
        ]);
    }

    public function assignStudents()
    {
        $paramsKey = [
            'class_id',
            'student_ids'
        ];

        if (!$this->validateParameterKeys($paramsKey)) {
            return $this->responseMissingParameters();
        }

        $class = $this->classRepository->getClassInfo($this->data['class_id'], $this->moetUnitId, ['mainTeacher']);
        if (is_null($class)) {
            return $this->responseNotFoundClass();
        }

        $this->classRepository->assignStudents($class, $this->data['student_ids']);

        return $this->respondSuccess();

    }

    public function detachStudents(){
        $paramsKey = [
            'class_id',
            'student_ids'
        ];

        if (!$this->validateParameterKeys($paramsKey)) {
            return $this->responseMissingParameters();
        }

        $class = $this->classRepository->getClassInfo($this->data['class_id'], $this->moetUnitId, ['mainTeacher']);
        if (is_null($class)) {
            return $this->responseNotFoundClass();
        }

        $this->classRepository->detachStudents($class->id, $this->data['student_ids']);

        return $this->respondSuccess();
    }
}
