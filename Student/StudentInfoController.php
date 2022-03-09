<?php

namespace App\Http\Controllers\Student;

use App\Entities\UserEntity;
use App\Helpers\Transformer\UserTransformer;
use App\Http\Controllers\Controller;
use App\Models\SchoolYear\SchoolYear;
use App\Models\Student\StudentHistory;
use App\Repositories\SchoolYear\SchoolYearRepository;
use App\Repositories\Student\StudentRepository;
use Illuminate\Http\Request;

class StudentInfoController extends Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $paramsKey = [
            'student_id'
        ];

        if (!$this->validateParameterKeys($paramsKey)) {
            return $this->responseMissingParameters();
        }

        $student = (new UserEntity())->getUserInfoById($this->data['student_id']);
        if (is_null($student)) {
            return $this->responseNotFoundOjbect();
        }

        $studentInfo = (new StudentRepository())->getCurrentClassInformation($student->id);
        $studentInfo = array_merge((new UserTransformer())->transform($student), [
            'department_name'  => $studentInfo['department_name'],
            'division_name'    => $studentInfo['division_name'],
            'school_year_name' => $studentInfo['school_year_name'],
            'school_name'      => $studentInfo['school_name'],
            'grade_name'       => $studentInfo['grade_name'],
            'stage'            => $studentInfo['stage'],
            'class_name'       => $studentInfo['class_name']
        ]);

        return $this->respondSuccess([
            'student'            => $studentInfo,
            'learning_histories' => $this->getLearnHistoriesOfStudent($student->id)
        ]);

    }

    private function getLearnHistoriesOfStudent($studentId)
    {
        $schoolYears = SchoolYear::where('status', 1)->orderBy('end_date', 'DESC')->get();

        $studentHistories = StudentHistory::where('user_id', $studentId)
            ->with([
                'school',
                'school_year',
                'grade',
                'thisClass'
            ])->get()
            ->groupBy(function ($history) {
                return $history->school_year_id;
            });

        $result = [];
        foreach ($schoolYears as $schoolYear) {
            $histories = isset($studentHistories[$schoolYear->id]) ? $studentHistories[$schoolYear->id] : collect();
            $histories = $histories->sortByDesc(function ($history) {
                return $history->term_id;
            });
            $mainHistory = count($histories) > 0 ? $histories->first() : null;
            $schoolName = !is_null($mainHistory) && !is_null($mainHistory->school) ? $mainHistory->school->name : '';
            $terms = [];
            foreach ($histories as $history) {
                $terms[] = [
                    'label'       => $history->getTermLabel(),
                    'school_name' => !is_null($history) && !is_null($history->school) ? $history->school->name : '',
                    'class_name'  => !is_null($history) && !is_null($history->thisClass) ? $history->thisClass->name : '',
                    'stage'       => !is_null($history) && !is_null($history->grade) ? $history->grade->getStageLabel() : '',
                    'is_current'  => $history->status == 1 ? 1 : 0
                ];
            }
            $result[] = [
                'school_year_name' => $schoolYear->name,
                'school_name'      => $schoolName,
                'terms'            => $terms
            ];
        }

        return $result;
    }
}
