<?php
/**
 *File name : NewsController.php / Date: 2/28/2022 - 11:15 PM
 *Code Owner: Thanhnt/ Email: Thanhnt@omt.com.vn/ Phone: 0384428234
 */

namespace App\Http\Controllers\HealthDeclaration;


use App\Entities\HealthDeclaration\HealthDeclarationTypeEntity;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Student\Student;
use App\Repositories\HealthDeclaration\HealthDeclarationRepository;
use App\Repositories\Student\StudentRepository;
use Carbon\Carbon;

class HealthDeclarationController extends Controller
{

    protected $entity;
    protected $validateCreate;
    protected $validateStore;
    protected $repository;
    protected $studentId;
    protected $student;

    public function __construct()
    {
        parent::__construct();
        $this->entity     = new HealthDeclarationTypeEntity();
        $this->repository = new HealthDeclarationRepository();
        $this->studentId  = $this->getStudentIdByData();
        $this->student    = Student::where('id', $this->studentId)->with('user')->first();
        if (is_null($this->student)) {
            throw new ApiException($this->responseError(trans($this->status['not_found']['student'])));
        }
        $this->data['student_id']   = $this->studentId;
        $this->data['moet_unit_id'] = $this->moetUnitId;
    }

    public function getStudentIdByData()
    {
        return !is_null(request()->header('studentId')) && trim(request()->header('studentId')) != '' ?
            (int)request()->header('studentId') : 0;
    }

    public function getTypes()
    {
        return $this->respondSuccess($this->entity->getAllParentTypes($this->student));
    }

    public function getQuestionHeightWeight()
    {
        if (!$this->validateParameterKeys(['type_id'])) {
            return $this->responseMissingParameters();
        }
        $type = $this->entity->getTypeInfo($this->data['type_id'],
            ['questions']);
        if (is_null($type)) {
            return $this->responseError("Có lỗi xảy ra vui lòng thử lại");
        }
        $date = isset($this->data['date']) ? Carbon::createFromTimestamp($this->data['date']) : Carbon::today();
        return $this->respondSuccess($this->entity->getQuestionHeightWeight($type, $this->student, $date));
    }

    public function updateStudentHeightWeight()
    {
        if (!$this->validateParameterKeys(['height', 'weight'])) {
            return $this->responseMissingParameters();
        }
        $studentRepo = new StudentRepository();
        $student     = $studentRepo->getById($this->data['student_id']);
        if (is_null($student)) {
            return $this->responseError(trans($this->status['not_found']['student']));
        }
        $this->repository->updateStudentHeightWeight($student, $this->data);
        return $this->respondSuccess($this->entity->getBmiResultWithHeightWeight($this->student));
    }

    public function getQuestionAnswer()
    {
        if (!$this->validateParameterKeys(['type_id'])) {
            return $this->responseMissingParameters();
        }
        $date = isset($this->data['date']) ? Carbon::createFromTimestamp($this->data['date']) : Carbon::today();
        $type = $this->entity->getTypeInfo($this->data['type_id'], ['questions', 'questions.answers']);
        if (is_null($type)) {
            return $this->responseError("Có lỗi xảy ra vui lòng thử lại");
        }
        return $this->respondSuccess($this->entity->getQuestionAnswerType($type, $this->studentId, $date));
    }

    public function updateStudentHealthResult()
    {
        if (!$this->validateParameterKeys(['results']) || !$this->validateDataResults($this->data['results'])) {
            return $this->responseMissingParameters();
        }
        return $this->respondSuccess($this->repository->updateStudentHealthResult($this->student, $this->data));
    }

    public function calculateStudentBmi()
    {
        if (!$this->validateParameterKeys(['height', 'weight'])) {
            return $this->responseMissingParameters();
        }
        return $this->respondSuccess($this->entity->getBmiResultWithHeightWeight($this->student));
    }

    private function validateDataResults($results)
    {
        foreach ($results as $index => $result) {
            if (!isset($result['question_id']) || !isset($result['answer_ids']))
            return false;
        }
        return true;
    }

}

