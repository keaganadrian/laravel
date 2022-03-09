<?php
/**
 *File name : TeacherController.php  / Date: 11/11/2021 - 4:55 PM
 *Code Owner: Dao Thi Minh Nguyet / Phone: 0985455294 / Email: nguyetdtm@omt.vn
 */

namespace App\Http\Controllers\MinistryOfEducation;

use App\Helpers\Transformer\QuestionBank\QuestionCategoryTransformer;
use App\Helpers\Transformer\QuestionBank\QuestionTransformer;
use App\Helpers\Transformer\Quiz\QuizTransformer;
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

class QuizController extends Controller
{

    protected $courseRepository;
    protected $quizRepository;
    protected $questionRepository;
    protected $quizQuestionRepository;
    protected $quizQuestionAnswerRepository;
    protected $questionAnswerRepository;
    protected $questionTransformer;
    protected $quizTransfomer;

    public function __construct()
    {
        parent::__construct();
        $this->quizRepository = new QuizRepository();
        $this->quizQuestionRepository = new QuizQuestionRepository();
        $this->quizQuestionAnswerRepository = new QuizQuestionAnswerRepository();
        $this->questionRepository = new QuestionRepository();
        $this->questionAnswerRepository = new QuestionAnswerRepository();
        $this->questionTransformer = new QuestionTransformer();
        $this->quizTransfomer = new QuizTransformer();
    }

    public function detail()
    {
        $data = request()->all();
        $validate = $this->validateEmptyData($data, ['quiz_id']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $quiz = $this->quizRepository->getData(['id' => $data['quiz_id']], ['quiz_question.answer', 'quiz_question_category'], [], 0, 0, ['*'], true);
        if (empty($quiz)) {
            return $this->responseError([""], trans('quiz.not_found_course'));
        }

        $quiz = $this->quizRepository->transformer($quiz);

        return $this->respondSuccess($quiz);
    }

    //Tạo bài kiểm tra
    public function create()
    {
        $data = $this->data;
        $validate = $this->validateEmptyData($data, ['name']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $dataCreate = [
            'code' => rand(1000000, 9999999),
            'name' => $data['name'],
            'moet_level' => MOET_UNIT_LEVEL_BO,
            'moet_unit_id' => $this->moetUnitId,
            'created_user_id' => Auth::check() ? Auth::user()->id : 0,
            'created_at' => Carbon::now()
        ];
        $quiz = $this->quizRepository->create($dataCreate);
        return $this->respondSuccess([]);
    }

    public function update()
    {
        $data = $this->data;
        $validate = $this->validateEmptyData($data, ['quiz_id', 'name', 'end_date', 'time', 'score']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $quiz_id = $data['quiz_id'];
        $dataUpdate['moet_level'] = MOET_UNIT_LEVEL_BO;
        $dataUpdate['moet_unit_id'] = $this->moetUnitId;
        $dataUpdate['name'] = $data['name'];
        $dataUpdate['description'] = @$data['description'];
        $dataUpdate['end_date'] = Carbon::createFromTimestamp($data['end_date']);
        $dataUpdate['time'] = $data['time'];
        $dataUpdate['score'] = $data['score'];
        $dataUpdate['sort_index'] = $data['sort_index'];
        $dataUpdate['view_result'] = $data['view_result'];
        $dataUpdate['random_question'] = $data['random_question'];
        $dataUpdate['created_at'] = Carbon::now();
        $dataUpdate['created_user_id'] = Auth::check() ? Auth::user()->id : 0;
        DB::beginTransaction();
        try {
            $this->quizRepository->update($dataUpdate, $quiz_id);
            $quiz = $this->quizRepository->getById($quiz_id);
            //Clear all question old in quiz
            $this->quizQuestionRepository->deleteByParam(['quiz_id' => $quiz_id]);
            $this->quizQuestionAnswerRepository->deleteByParam(['quiz_id' => $data['quiz_id']]);
            //Create question in quiz
            $this->createQuestionInQuiz($quiz, $this->data['list_question']);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->responseError(trans('api.server_error'));
        }

        return $this->respondSuccess([]);
    }

    //Thêm câu hỏi vào bài kiểm tra
    public function createQuestionInQuiz($quiz, $list_question)
    {
        if (empty($list_question)) {
            return true;
        }
        $data_create_quiz_question_answer = [];
        foreach ($list_question as $question) {
            $question_id = $question['question_id'];
            $score = $question['score'];
            $sort_index = $question['sort_index'];
            $question_info = $this->questionRepository->getData(['id' => $question_id], ['answer'], [], 0, 0, ['*'], true);
            if (!empty($question_info)) {
                $answers = $question_info->answer;
                $data_create_quiz_question['quiz_id'] = $quiz->id;
                $data_create_quiz_question['moet_level'] = MOET_UNIT_LEVEL_BO;
                $data_create_quiz_question['question_id'] = $question_info->id;
                $data_create_quiz_question['question_name'] = $question_info->name;
                $data_create_quiz_question['content'] = $question_info->content;
                $data_create_quiz_question['solution_guide'] = $question_info->solution_guide;
                $data_create_quiz_question['type'] = $question_info->type;
                $data_create_quiz_question['score'] = $score;
                $data_create_quiz_question['sort_index'] = $sort_index;
                $data_create_quiz_question['status'] = $question_info->status;
                $data_create_quiz_question['created_user_id'] = Auth::check() ? Auth::user()->id : 0;
                $quiz_question = $this->quizQuestionRepository->create($data_create_quiz_question);
                if (count($answers) > 0) {
                    foreach ($answers as $answer) {
                        $data_create_quiz_question_answer[] = [
                            "quiz_id" => $quiz->id,
                            "moet_level" => MOET_UNIT_LEVEL_BO,
                            "question_id" => $answer->question_id,
                            "quiz_question_id" => $quiz_question->id,
                            "content" => $answer->content,
                            "is_true" => $answer->is_true,
                            "percent_score" => $answer->percent_score,
                            "created_user_id" => Auth::check() ? Auth::user()->id : 0
                        ];
                    }
                }
            }
        }
        $this->quizQuestionAnswerRepository->bulkInsert($data_create_quiz_question_answer);
    }

    public function delete()
    {
        $data = $this->data;
        $this->quizRepository->delete($data['quiz_id']);
        $this->quizQuestionRepository->deleteByParam(['quiz_id' => $data['quiz_id']]);
        $this->quizQuestionAnswerRepository->deleteByParam(['quiz_id' => $data['quiz_id']]);
        return $this->respondSuccess([]);
    }

}

