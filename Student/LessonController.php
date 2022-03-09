<?php


namespace App\Http\Controllers\Student;

use App\Helpers\Transformer\Lesson\LessonTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\Lesson\LessonClassRepository;
use App\Repositories\Lesson\LessonCompleteRepository;
use App\Repositories\Lesson\LessonRepository;
use App\Repositories\Quiz\QuizRepository;
use App\Repositories\SchoolYear\SchoolYearRepository;
use App\Repositories\Student\StudentRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    protected $lessonTransformer;
    protected $lessonRepository;
    protected $quizRepository;
    protected $lessonClassRepository;
    protected $lessonCompleteRepository;
    protected $studentRepository;
    protected $lessonClassTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->quizRepository = new QuizRepository();
        $this->lessonClassRepository = new LessonClassRepository();
        $this->lessonRepository = new LessonRepository();
        $this->lessonCompleteRepository = new LessonCompleteRepository();
        $this->lessonTransformer = new LessonTransformer();
        $this->lessonClassTransformer = new LessonTransformer();
        $this->studentRepository = new StudentRepository();
    }

    public function listLessonByClass()
    {
        $data = \request()->all();
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

    public function listLessonOfStudent()
    {
        $data = \request()->all();

        $schoolYearRepository = new SchoolYearRepository();
        $schoolYear = isset($this->data['school_year_id']) ?
            $schoolYearRepository->getSchoolYearById($this->data['school_year_id']) :
            $schoolYearRepository->getCurrentSchoolYearInfo();
        $schoolYearId = !is_null($schoolYear) ? $schoolYear->id : 0;

        $user_id = Auth::check() ? Auth::user()->id : 0;
        $class_of_students = $this->studentRepository->getData(['user_id' => $user_id, 'school_id' => $this->moetUnitId, 'school_year_id' => $schoolYearId]);
        $class_id_of_students = [];
        if (count($class_of_students) > 0) {
            $class_id_of_students = $class_of_students->pluck('class_id')->toArray();
        }
        $list_lesson_of_class = $this->lessonClassRepository->getListLessonByClass($class_id_of_students, @$data['keyword_search']);
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
        $uaer_id = Auth::check() ? Auth::user()->id : 0;
        $list_content_complete = $this->lessonCompleteRepository->getData(['lesson_id' => $data['lesson_id'], 'class_id' => $data['class_id'], 'user_id' => $uaer_id]);
        $data_result = $this->lessonClassTransformer->transformCollectionKeepFormat($list_content_of_lesson->all());
        $data_rs_new = [];
        $completed_lesson = 0;
        foreach ($data_result as $data_lesson) {
            $lesson_content = $data_lesson['lesson_content'];
            $lesson_link = $data_lesson['lesson_link'];
            $lesson_file = $data_lesson['lesson_file'];
            $lesson_quiz = $data_lesson['lesson_quiz'];
            if (!empty($lesson_content)) {
                foreach ($lesson_content as $k1 => $v_lesson_content) {
                    $check_complete = $list_content_complete->where('lesson_type', 0)->where('lesson_type_id', $v_lesson_content['lesson_content_id'])->count();
                    $is_completed = $check_complete > 0 ? 1 : 0;
                    $data_lesson['lesson_content'][$k1]['completed_learn'] = $is_completed;
                }
            }
            if (!empty($lesson_link)) {
                foreach ($lesson_link as $k1 => $l_lesson_link) {
                    $check_complete1 = $list_content_complete->where('lesson_type', 2)->where('lesson_type_id', $l_lesson_link['lesson_link_id'])->count();
                    $is_complete2 = $check_complete1 > 0 ? 1 : 0;
                    $data_lesson['lesson_link'][$k1]['completed_learn'] = $is_complete2;
                }
            }
            if (!empty($lesson_file)) {
                foreach ($lesson_file as $k1 => $f_lesson_content) {
                    $check_complete2 = $list_content_complete->where('lesson_type', 1)->where('lesson_type_id', $f_lesson_content['lesson_file_id'])->count();
                    $is_complete3 = $check_complete2 > 0 ? 1 : 0;
                    $data_lesson['lesson_file'][$k1]['completed_learn'] = $is_complete3;
                }
            }
            if (!empty($lesson_quiz)) {
                foreach ($lesson_quiz as $k1 => $q_lesson_content) {
                    $check_complete3 = $list_content_complete->where('lesson_type', 3)->where('lesson_type_id', $q_lesson_content['quiz_id'])->count();
                    $is_complete4 = $check_complete3 > 0 ? 1 : 0;
                    $data_lesson['lesson_quiz'][$k1]['completed_learn'] = $is_complete4;
                }
            }
            if ($is_complete2 && $is_complete3 && $is_complete4 && $is_completed)
                $completed_lesson = 1;
            $data_lesson['lesson_completed'] = $completed_lesson;
            $data_rs_new[] = $data_lesson;
        }
        return $this->respondSuccess($data_rs_new);
    }

    public function finishContent(Request $request)
    {
        $data = $request->all();
        $validate = $this->validateEmptyData($data, ['lesson_type', 'lesson_type_id', 'class_id', 'lesson_id']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $data_create = [
            'user_id' => Auth::user()->id,
            'lesson_id' => $data['lesson_id'],
            'class_id' => $data['class_id'],
            'lesson_type' => $data['lesson_type'],
            'lesson_type_id' => $data['lesson_type_id'],
            'created_user_id' => Auth::user()->id,
            'created_at' => Carbon::now()
        ];
        $this->lessonCompleteRepository->create($data_create);
        return $this->respondSuccess([]);
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
