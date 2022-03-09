<?php


namespace App\Http\Controllers\Teacher;

use App\Helpers\Transformer\Lesson\LessonTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\Lesson\LessonClassRepository;
use App\Repositories\Lesson\LessonRepository;
use App\Repositories\Quiz\QuizRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    protected $lessonTransformer;
    protected $lessonRepository;
    protected $lessonClassTransformer;
    protected $quizRepository;
    protected $lessonClassRepository;

    public function __construct()
    {
        parent::__construct();
        $this->quizRepository = new QuizRepository();
        $this->lessonClassRepository = new LessonClassRepository();
        $this->lessonRepository = new LessonRepository();
        $this->lessonClassTransformer = new LessonTransformer();
        $this->lessonTransformer = new LessonTransformer();
    }

    public function listLesson()
    {
        $data = request()->all();

        $parent_moet_unit = 2;//TODO ddang fix cung de lay data


        $page = empty($data['page']) ? 1 : $data['page'];
        $limit = empty($data['limit']) ? 10 : $data['limit'];
        $data_lesson = $this->lessonRepository->getListLesson(['moet_unit_id' => $parent_moet_unit], $page, $limit, @$data['keyword_search']);
        if (count($data_lesson) <= 0) {
            return $this->respondSuccess([]);
        }
        $data_lesson_result = [];
        foreach ($data_lesson as $value) {
            $list_class = '';
            $class_ids = [];
            if (count($value->lesson_class) > 0) {
                $arr_class = $value->lesson_class->pluck('classes')->flatten();
                $list_class = implode(',', $arr_class->pluck('name')->filter()->toArray());
                $class_ids = $arr_class->pluck('id')->filter()->toArray();
            }

            $data_lesson_result[] = [
                'lesson_id' => $value->id,
                'lesson_name' => $value->name,
                'description' => $value->description,
                'total_content' => $value->lesson_content_count,
                'total_quiz' => $value->lesson_quiz_count,
                'list_class' => $list_class,
                'list_class_ids' => $class_ids,
                'total_student' => empty($value->created_user) ? "" : getFullNameOfUser($value->created_user->toArray())
            ];
        }
        $data_result = [
            'total' => $data_lesson->total(),
            'limit' => $limit,
            'current_page' => $page,
            'data' => $data_lesson_result
        ];
        return $this->respondSuccess($data_result);
    }

    public function assignLessonToclass()
    {
        $data = $this->data;
        $validate = $this->validateEmptyData($data, ['class_ids', 'lesson_id']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $class_ids = $data['class_ids'];
        $lesson_id = $data['lesson_id'];
        $data_create = [];
        foreach ($class_ids as $class_id) {
            $data_create[] = [
                'lesson_id' => $lesson_id,
                'class_id' => $class_id,
                'moet_unit_id' => $this->moetUnitId,
                'sort_index' => empty($data['sort_index']) ? 1 : $data['sort_index'],
                'created_user_id' => Auth::check() ? Auth::user()->id : 0,
            ];
        }
        DB::beginTransaction();
        try {
            //Xoa hien tai
            $this->lessonClassRepository->deleteByParam(['lesson_id' => $lesson_id]);
            //Them moi
            $this->lessonClassRepository->bulkInsert($data_create);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->responseError(trans('api.server_error'));
        }
        return $this->respondSuccess([]);
    }

    public function classUseLesson()
    {
        $data = $this->data;
        $validate = $this->validateEmptyData($data, ['lesson_id']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $data_lesson_class = $this->lessonClassRepository->getData(['lesson_id' => $data['lesson_id']], ['classes']);
        if (count($data_lesson_class) <= 0) {
            return $this->respondSuccess([]);
        }
        $data_result = [];
        foreach ($data_lesson_class as $lesson_class) {
            $class = $lesson_class->classes;
            if (!empty($class)) {
                $data_result[] = [
                    'class_id' => $class->id,
                    'name' => $class->name,
                ];
            }
        }
        return $this->respondSuccess($data_result);
    }

    public function quizDetail()
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
}
