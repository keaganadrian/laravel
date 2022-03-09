<?php


namespace App\Http\Controllers\Student;

use App\Helpers\Transformer\Assignment\AssignmentTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\Assignment\AssignmentDetailRepository;
use App\Repositories\Assignment\AssignmentRepository;
use App\Repositories\Quiz\QuizRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{

    protected $assignmentRepository;
    protected $assignmentDetailRepository;
    protected $assignmentTransformer;
    protected $quizRepository;
    protected $user_id;

    public function __construct()
    {
        parent::__construct();
        $this->assignmentRepository = new AssignmentRepository();
        $this->assignmentDetailRepository = new AssignmentDetailRepository();
        $this->quizRepository = new QuizRepository();
        $this->assignmentTransformer = new AssignmentTransformer();
        $this->user_id = Auth::check() ? Auth::user()->id : 0;
    }

    /*
     * Bắt đầu làm bài quiz
     */
    public function startAssignment()
    {
        $data = $this->data;
        $validate = $this->validateEmptyData($data, ['quiz_id', 'class_id']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $quiz_info = $this->quizRepository->getData(['id' => $data['quiz_id']], [], [], 0, 0, ['*'], true);
        if (empty($quiz_info)) {
            return $this->responseError(trans('quiz.not_found_course'), []);
        }

        $assignment_of_student = $this->assignmentRepository->getData(['quiz_id' => $data['quiz_id'], 'user_id' => $this->user_id]);
        //Kiểm tra xem học viên có đang làm bài hay không
        if (count($assignment_of_student) > 0) {
            if (count($assignment_of_student->where('status', 0)) > 0) {
                $assignment_info = $assignment_of_student->where('status', 0)->first();
                //Kiểm tra xem còn bao nhiêu thời gian làm bài
                $current_time = Carbon::now()->timestamp;
                $time_quiz = $quiz_info->time;//Thời gian làm bài quiz
                $end_time = Carbon::parse($assignment_info->start_time)->addMinutes($time_quiz)->timestamp;//Thời gian bắt đầu làm
                if ($current_time >= $end_time) {
                    return $this->responseError(trans('quiz.expired_time'), []);
                }
                $time_expired = $end_time - $current_time;
                $assignment_result = $this->assignmentTransformer->transform($assignment_info);
                $assignment_result['time_expired'] = $time_expired;
                return $this->respondSuccess($assignment_result);
            }
        }
        // Tạo vào bảng assignment
        $data_create = [
            'moet_level' => MOET_UNIT_LEVEL_SCHOOL,
            'moet_unit_id' => $this->moetUnitId,
            'lesson_id' => $quiz_info->lesson_id,
            'class_id' => $data['class_id'],
            'user_id' => $this->user_id,
            'quiz_id' => $quiz_info->id,
            'start_time' => Carbon::now(),
            'end_time' => null,
            'version' => count($assignment_of_student) + 1,
            'status' => 0,
            'view_result' => $quiz_info->view_result,
            'score' => 0,
            'time' => 0,
            'created_user_id' => $this->user_id,
        ];
        $assignment_info = $this->assignmentRepository->create($data_create);
        if (empty($assignment_info)) {
            return $this->responseError(trans('quiz.error'), []);
        }
        $assignment_result = $this->assignmentTransformer->transform($assignment_info);
        $assignment_result['time_expired'] = $quiz_info->time * 60;
        return $this->respondSuccess($assignment_result);
    }

    public function infoAssignment()
    {
        $data = $this->data;
        $validate = $this->validateEmptyData($data, ['assignment_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }

        $assignment_info = $this->assignmentRepository->getData(['id' => $data['assignment_id']], [], [], 0, 0, ['*'], true);
        if (empty($assignment_info)) {
            return $this->respondSuccess([]);
        }
        $quiz_info = $this->quizRepository->find($assignment_info->quiz_id);
        if (empty($quiz_info)) {
            return $this->respondSuccess([]);
        }
        //Tính toán thời gian còn lại
        $current_time = Carbon::now()->timestamp;
        $time_quiz = $quiz_info->time;//Thời gian làm bài quiz
        $end_time = Carbon::parse($assignment_info->start_time)->addMinutes($time_quiz)->timestamp;//Thời gian bắt đầu làm
        if ($current_time >= $end_time) {
            $time_expired = 0;
        } else {
            $time_expired = $end_time - $current_time;
        }

        $assignment_result = $this->assignmentTransformer->transform($assignment_info);
        $assignment_result['time_expired'] = $time_expired;
        return $this->respondSuccess($assignment_result);
    }

    public function submitAssignment()
    {
        $data = $this->data;
        $validate = $this->validateEmptyData($data, ['assignment_id']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $assignment_info = $this->assignmentRepository->getById($data['assignment_id']);
        if (!empty($assignment_info) && $assignment_info->status != 0) {
            return $this->responseError(trans('quiz.assignment_has_complete'), $validate);
        }
        $answers = $data['answer'];
        if (empty($answers)) {
            return [];
        }
        foreach ($answers as $key => $answer) {
            $answers[$key]['moet_level'] = MOET_UNIT_LEVEL_SCHOOL;
            $answers[$key]['moet_unit_id'] = $this->moetUnitId;
            $answers[$key]['assignment_id'] = $data['assignment_id'];
            $answers[$key]['score'] = 0;
            $answers[$key]['status'] = 1;//1: đã hoàn thành chưa chấm điểm; 2: Đã chấm điểm
            $answers[$key]['answer'] = is_array($answer['answer']) ? json_encode($answer['answer']) : $answer['answer'];
            unset($answers[$key]['time']);
        }
        DB::beginTransaction();
        try {
            $this->assignmentRepository->update(['status' => 1, 'time' => @$data['time']], $data['assignment_id']);
            $this->assignmentDetailRepository->bulkInsert($answers);
            DB::commit();
        } catch (\Exception $exception) {
            dd($exception);
            DB::rollBack();
            return $this->responseError(trans('api.server_error'));
        }
        return $this->respondSuccess([]);
    }

    public function historyAssignment()
    {
        $data = $this->data;
        $validate = $this->validateEmptyData($data, ['quiz_id', 'class_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $param = [
            'quiz_id' => $data['quiz_id'],
            'class_id' => $data['class_id'],
            'user_id' => $this->user_id
        ];
        $list_assignment = $this->assignmentRepository->getData([$param]);
        $data_result = [];
        if (count($list_assignment) > 0) {
            $data_result = $this->assignmentTransformer->transformCollectionKeepFormat($list_assignment->all());
        }
        return $this->respondSuccess($data_result);
    }

    public function detailAssignment()
    {
        $data = $this->data;
        $validate = $this->validateEmptyData($data, ['assignment_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $assignment_info = $this->assignmentRepository->getById($data['assignment_id']);
        if (empty($assignment_info)) {
            return $this->respondSuccess([]);
        }
        $quiz_id = $assignment_info->quiz_id;
        $quiz = $this->quizRepository->getData(['id' => $quiz_id], ['quiz_question.answer', 'quiz_question_category'], [], 0, 0, ['*'], true);
        if (empty($quiz)) {
            return $this->responseError([], trans('quiz.not_found_course'));
        }

        $data_result = $this->quizRepository->transformerWithResult($assignment_info, $quiz);
        return $this->respondSuccess($data_result);
    }

}
