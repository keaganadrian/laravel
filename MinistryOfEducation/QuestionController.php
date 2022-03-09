<?php
/**
 *File name : TeacherController.php  / Date: 11/11/2021 - 4:55 PM
 *Code Owner: Dao Thi Minh Nguyet / Phone: 0985455294 / Email: nguyetdtm@omt.vn
 */

namespace App\Http\Controllers\MinistryOfEducation;

use App\Helpers\Transformer\QuestionBank\QuestionCategoryTransformer;
use App\Helpers\Transformer\QuestionBank\QuestionTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\QuestionBank\QuestionAnswerRepository;
use App\Repositories\QuestionBank\QuestionCategoryRepository;
use App\Repositories\QuestionBank\QuestionRepository;
use App\Repositories\Quiz\QuizQuestionAnswerRepository;
use App\Repositories\Quiz\QuizQuestionRepository;
use App\Repositories\Quiz\QuizRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{

    protected $courseRepository;
    protected $quizRepository;
    protected $questionRepository;
    protected $quizQuestionRepository;
    protected $quizQuestionAnswerRepository;
    protected $questionAnswerRepository;
    protected $questionTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->quizRepository = new QuizRepository();
        $this->quizQuestionRepository = new QuizQuestionRepository();
        $this->quizQuestionAnswerRepository = new QuizQuestionAnswerRepository();
        $this->questionRepository = new QuestionRepository();
        $this->questionAnswerRepository = new QuestionAnswerRepository();
        $this->questionTransformer = new QuestionTransformer();
    }

    public function getListData()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['question_category_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }

        $keyword_search = @$data_input['keyword_search'];
        $page = @$data_input['page'];
        if (empty($page)) {
            $page = 1;
        }
        $take = 100;
        if ($page == 1) {
            $skip = 0;
        } else {
            $skip = 100 * ($page - 1);
        }
        $data_condition = [];
        if (!empty($data_input['question_category_id'])) {
            $data_condition['question_category_id'] = $data_input['question_category_id'];
        }
//        $data_condition['created_user_id'] = empty(Auth::user()->id) ? "" : Auth::user()->id;

        if (!empty($keyword_search)) {
            $data_condition['keyword_search'] = $keyword_search;
        }
        $data_category = $this->questionRepository->getData($data_condition, ['answer'], [], $skip, $take);
        if (count($data_category) <= 0) {
            $data_result = [];
        } else {
            $data_result = $this->questionTransformer->transformCollectionKeepFormat($data_category->all());
        }
        return $this->respondSuccess($data_result);
    }

    public function detail()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['question_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $question_info = $this->questionRepository->getData(['id' => $data_input['question_id']], ['answer'], [], 0, 0, '*', true);
        if (empty($question_info))
            return $this->respondSuccess([]);
        $data_result = $this->questionTransformer->transform($question_info);
        return $this->respondSuccess($data_result);

    }

    public function createQuestion()
    {
        $data_input = request()->all();
        $validate = $this->validateEmptyData($data_input, ['question_category_id', 'name', 'content']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        if (in_array($data_input['type'], [ONE_CHOICE, MULTIPLE_CHOICE])) {
            if (empty($data_input['answer'])) {
                return $this->responseError(trans('api.param_requered', ['param' => "answer"]), trans('api.param_error'));
            }
        }
        //Tách câu trả lời
        $answer = $data_input['answer'];
        unset($data_input['answer']);

        $data_input['code'] = $this->questionRepository->genCodeQuestion();
        $data_input['moet_level'] = MOET_UNIT_LEVEL_BO;
        $data_input['moet_unit_id'] = $this->moetUnitId;
        $data_input['created_at'] = Carbon::now();
        $data_input['created_user_id'] = Auth::check() ? Auth::user()->id : 0;
        DB::beginTransaction();
        try {
            $question_info = $this->questionRepository->create($data_input);
            //Chỉ tạo câu trả lời cho câu hỏi chọn đáp án đúng
            if (!empty($answer)) {
                $this->createQuestionAnswer($question_info, $answer);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return $this->responseError(trans('api.error_server'), []);
        }

        return $this->respondSuccess([]);
    }

    public function createQuestionAnswer($question_info, $answers)
    {
        $data_insert_answer = [];
        foreach ($answers as $answer) {
            $answer['question_id'] = $question_info->id;
            $answer['created_at'] = Carbon::now();
            $answer['created_user_id'] = Auth::check() ? Auth::user()->id : 0;
            $answer['moet_level'] = MOET_UNIT_LEVEL_BO;
//            $answer['content'] = createFileFromContentBase64($answer['content']);
            $data_insert_answer[] = $answer;
        }
        $this->questionAnswerRepository->bulkInsert($data_insert_answer);
        return true;
    }

    public function update()
    {
        $data_input = request()->all();
        $validate = $this->validateEmptyData($data_input, ['question_id', 'name', 'content']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $data_update = [
            'name' => $data_input['name'],
            'content' => $data_input['content'],
            'solution_guide' => $data_input['solution_guide'],
            'status' => $data_input['status'],
        ];
        $this->questionRepository->update($data_update, $data_input['question_id']);

        $question_info = $this->questionRepository->find($data_input['question_id']);
        if (in_array($question_info->type, [ONE_CHOICE, MULTIPLE_CHOICE])) {
            $answer = $data_input['answer'];
            //Xóa đáp án câu hỏi hiện tại
            $this->questionAnswerRepository->deleteByParam(['question_id' => $data_input['question_id']]);
            //Thêm câu trả lời cho câu hỏi
            $this->createQuestionAnswer($question_info, $answer);
        }
        return $this->respondSuccess([]);
    }

    public function delete()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['question_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $this->questionRepository->delete($this->data['question_id']);
        $this->questionAnswerRepository->deleteByParam(['question_id' => $this->data['question_id']]);

        return $this->respondSuccess([]);
    }
}

