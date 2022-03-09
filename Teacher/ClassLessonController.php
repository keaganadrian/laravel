<?php


namespace App\Http\Controllers\Teacher;

use App\Entities\UserEntity;
use App\Helpers\Transformer\Lesson\LessonTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\Lesson\LessonClassRepository;
use App\Repositories\Lesson\LessonRepository;
use App\Repositories\Quiz\QuizRepository;
use Illuminate\Http\Request;

class ClassLessonController extends Controller
{
    protected $lessonClassRepository;
    protected $lessonRepository;
    protected $lessonClassTransformer;
    protected $quizRepository;

    public function __construct()
    {
        parent::__construct();
        $this->quizRepository = new QuizRepository();
        $this->lessonClassRepository = new LessonClassRepository();
        $this->lessonClassTransformer = new LessonTransformer();
        $this->lessonRepository = new LessonRepository();
    }

    public function getListLesson(Request $request)
    {
        $data = $request->all();
        $validate = $this->validateEmptyData($data, ['class_id']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $list_lesson_of_class = $this->lessonClassRepository->getListLessonByClass($data['class_id'], @$data['keyword_search']);
        if (count($list_lesson_of_class) <= 0) {
            return $this->respondSuccess([]);
        }
        $data_result = [];
        foreach ($list_lesson_of_class as $lesson_of_class) {
            $data_result[] = [
                'lesson_id' => $lesson_of_class->lesson_id,
                'lesson_name' => $lesson_of_class->lesson->name,
                'description' => $lesson_of_class->lesson->description,
                'total_content' => $lesson_of_class->lesson->lesson_content_count,
                'total_quiz' => $lesson_of_class->lesson->lesson_quiz_count,
                'created_user_id' => empty($lesson_of_class->created_user) ? 0 : $lesson_of_class->created_user->id,
                'created_user_name' => empty($lesson_of_class->created_user) ? "" : getFullNameOfUser($lesson_of_class->created_user->toArray()),
                'class_id' => empty($lesson_of_class->classes) ? 0 : $lesson_of_class->classes->id,
                'class_name' => empty($lesson_of_class->classes) ? "" : $lesson_of_class->classes->name
            ];
        }
        return $this->respondSuccess($data_result);
    }

    public function getListContentOfLesson(Request $request)
    {
        $data = $request->all();
        $validate = $this->validateEmptyData($data, ['class_id', 'lesson_id']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $list_content_of_lesson = $this->lessonRepository->getData(['lesson_id'], ['lesson_content', 'lesson_file', 'lesson_link', 'lesson_quiz']);
        if (count($list_content_of_lesson) <= 0) {
            return $this->respondSuccess([]);
        }
        return $this->lessonClassTransformer->transformCollectionKeepFormat($list_content_of_lesson->all());
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
