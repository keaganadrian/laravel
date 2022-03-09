<?php

namespace App\Http\Controllers\MinistryOfEducation;

use App\Entities\FileEntity;
use App\Helpers\Transformer\Lesson\LessonClassTransformer;
use App\Helpers\Transformer\Lesson\LessonCompleteTransformer;
use App\Helpers\Transformer\Lesson\LessonContentTransformer;
use App\Helpers\Transformer\Lesson\LessonFileTransformer;
use App\Helpers\Transformer\Lesson\LessonLinkTransformer;
use App\Helpers\Transformer\Lesson\LessonTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\Lesson\LessonClassRepository;
use App\Repositories\Lesson\LessonContentRepository;
use App\Repositories\Lesson\LessonFileRepository;
use App\Repositories\Lesson\LessonLinkRepository;
use App\Repositories\Lesson\LessonRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManagerLessonController extends Controller
{

    protected $lessonTransformer;
    protected $lessonRepository;
    protected $lessonClassTransformer;
    protected $lessonContentTransformer;
    protected $lessonLinkTransformer;
    protected $lessonFileTransformer;
    protected $lessonCompleteTransformer;
    protected $lessonClassRepository;
    protected $lessonContentRepository;
    protected $lessonFileRepository;
    protected $lessonLinkRepository;
    protected $lessonCompleteRepository;

    public function __construct()
    {
        parent::__construct();
        $this->lessonClassRepository = new LessonClassRepository();
        $this->lessonRepository = new LessonRepository();
        $this->lessonCompleteRepository = new LessonContentRepository();
        $this->lessonContentRepository = new LessonContentRepository();
        $this->lessonFileRepository = new LessonFileRepository();
        $this->lessonLinkRepository = new LessonLinkRepository();


        $this->lessonClassTransformer = new LessonClassTransformer();
        $this->lessonTransformer = new LessonTransformer();
        $this->lessonLinkTransformer = new LessonLinkTransformer();
        $this->lessonFileTransformer = new LessonFileTransformer();
        $this->lessonContentTransformer = new LessonContentTransformer();
        $this->lessonCompleteTransformer = new LessonCompleteTransformer();
    }

    public function getListData(){

    }

    public function create(){
        $param_require = [
            'code',
            'moet_unit_id',
            'moet_level',
            'name',
            'description',
            'sort_index'
        ];
        $data_input = $this->data;
        $data_input['created_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $this->lessonRepository->create($data_input);
        return $this->respondSuccess([]);
    }

    public function update(){
        $param_require = [
            'lesson_id'
        ];
        $data_input = $this->data;
        $data_input['modified_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $this->lessonRepository->update($data_input,$data_input['lesson_id']);
        return $this->respondSuccess([]);
    }

    public function delete(){

        $param_require = [
            'lesson_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson = $this->lessonRepository->find($data_input['lesson_id']);
        if (empty($lesson)){
            return $this->responseError(trans('ministry.not_found_lesson'));
        }

        $this->lessonRepository->delete($data_input['lesson_id']);
        $this->lessonContentRepository->deleteByParam(['lesson_id' => $data_input['lesson_id']]);
        $this->lessonLinkRepository->deleteByParam(['lesson_id' => $data_input['lesson_id']]);
        $this->lessonCompleteRepository->deleteByParam(['lesson_id' => $data_input['lesson_id']]);
        $this->lessonClassRepository->deleteByParam(['lesson_id' => $data_input['lesson_id']]);
        $this->lessonFileRepository->deleteByParam(['lesson_id' => $data_input['lesson_id']]);
        return $this->respondSuccess([]);
    }

    public function detail(){
        $param_require = [
            'lesson_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_info = $this->lessonRepository->getLessionDetail($data_input['lesson_id']);
        $lesson_info = $this->lessonTransformer->transform($lesson_info);
        return $this->respondSuccess($lesson_info);
    }

    // Content
    public function createContent(){


        $param_require = [
            'lesson_id',
            'name',
            'content',
            'sort_index'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson = $this->lessonRepository->find($data_input['lesson_id']);
        if (empty($lesson)){
            return $this->responseError(trans('ministry.not_found_lesson'));
        }

        $data_input['created_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->lessonContentRepository->create($data_input);
        return $this->respondSuccess([]);
    }

    public function updateContent(){
        $param_require = [
            'name',
            'content',
            'sort_index',
            'lesson_content_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_content = $this->lessonContentRepository->find($data_input['lesson_content_id']);
        if (empty($lesson_content)){
            return $this->responseError(trans('ministry.not_found_lesson_content'));
        }

        $data_input['modified_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->lessonContentRepository->update($data_input, $data_input['lesson_content_id']);
        return $this->respondSuccess([]);
    }

    public function detailContent(){
        $param_require = [
            'lesson_content_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_content = $this->lessonContentRepository->find($data_input['lesson_content_id']);
        if (empty($lesson_content)){
            return $this->responseError(trans('ministry.not_found_lesson_content'));
        }
        $lesson_content = $this->lessonContentTransformer->transform($lesson_content);
        return $this->respondSuccess($lesson_content);
    }

    public function deleteContent(){
        $param_require = [
            'lesson_content_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_content = $this->lessonContentRepository->find($data_input['lesson_content_id']);
        if (empty($lesson_content)){
            return $this->responseError(trans('ministry.not_found_lesson_content'));
        }

        $data_input['modified_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->lessonContentRepository->delete($data_input['lesson_content_id']);
        return $this->respondSuccess([]);
    }

    // Link
    public function createLink(){


        $param_require = [
            'lesson_id',
            'name',
            'link',
            'sort_index'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson = $this->lessonRepository->find($data_input['lesson_id']);
        if (empty($lesson)){
            return $this->responseError(trans('ministry.not_found_lesson'));
        }

        $data_input['created_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->lessonLinkRepository->create($data_input);
        return $this->respondSuccess([]);
    }

    public function updateLink(){
        $param_require = [
            'name',
            'link',
            'sort_index',
            'lesson_link_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_link = $this->lessonLinkRepository->find($data_input['lesson_link_id']);
        if (empty($lesson_link)){
            return $this->responseError(trans('ministry.not_found_lesson_link'));
        }

        $data_input['modified_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->lessonLinkRepository->update($data_input, $data_input['lesson_link_id']);
        return $this->respondSuccess([]);
    }

    public function detailLink(){
        $param_require = [
            'lesson_link_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_link = $this->lessonLinkRepository->find($data_input['lesson_link_id']);
        if (empty($lesson_link)){
            return $this->responseError(trans('ministry.not_found_lesson_link'));
        }
        $lesson_link = $this->lessonLinkRepository->transform($lesson_link);
        return $this->respondSuccess($lesson_link);
    }

    public function deleteLink(){
        $param_require = [
            'lesson_link_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_link = $this->lessonLinkRepository->find($data_input['lesson_link_id']);
        if (empty($lesson_link)){
            return $this->responseError(trans('ministry.not_found_lesson_link'));
        }

        $data_input['modified_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->lessonLinkRepository->delete($data_input['lesson_link_id']);
        return $this->respondSuccess([]);
    }

    // File
    public function createFile(){


        $param_require = [
            'lesson_id',
            'name',
            'file',
            'sort_index'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson = $this->lessonRepository->find($data_input['lesson_id']);
        if (empty($lesson)){
            return $this->responseError(trans('ministry.not_found_lesson'));
        }

        $path = (new FileEntity())->saveFileAmazonBase64($data_input['file'],
            $lesson->code . Carbon::now()->timestamp, 'lesson/file', false);

        $data_input['created_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->lessonFileRepository->create($data_input);
        return $this->respondSuccess([]);
    }

    public function updateFile(){
        $param_require = [
            'name',
            'link',
            'sort_index',
            'lesson_link_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_link = $this->lessonLinkRepository->find($data_input['lesson_link_id']);
        if (empty($lesson_link)){
            return $this->responseError(trans('ministry.not_found_lesson_link'));
        }

        $data_input['modified_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->lessonLinkRepository->update($data_input, $data_input['lesson_link_id']);
        return $this->respondSuccess([]);
    }

    public function detailFile(){
        $param_require = [
            'lesson_link_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_link = $this->lessonLinkRepository->find($data_input['lesson_link_id']);
        if (empty($lesson_link)){
            return $this->responseError(trans('ministry.not_found_lesson_link'));
        }
        $lesson_link = $this->lessonLinkRepository->transform($lesson_link);
        return $this->respondSuccess($lesson_link);
    }

    public function deleteFile(){
        $param_require = [
            'lesson_link_id'
        ];
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, $param_require);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }

        $lesson_link = $this->lessonLinkRepository->find($data_input['lesson_link_id']);
        if (empty($lesson_link)){
            return $this->responseError(trans('ministry.not_found_lesson_link'));
        }

        $data_input['modified_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->lessonLinkRepository->delete($data_input['lesson_link_id']);
        return $this->respondSuccess([]);
    }

}
