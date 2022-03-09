<?php
/**
 *File name : TeacherController.php  / Date: 11/11/2021 - 4:55 PM
 *Code Owner: Dao Thi Minh Nguyet / Phone: 0985455294 / Email: nguyetdtm@omt.vn
 */

namespace App\Http\Controllers\MinistryOfEducation;

use App\Helpers\Transformer\QuestionBank\QuestionCategoryTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\QuestionBank\QuestionCategoryRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class QuestionCategoryController extends Controller
{

    protected $entity;
    protected $validateCreate;
    protected $questionCategoryRepository;
    protected $questionCategoryTransformer;

    public function __construct()
    {
        parent::__construct();
        $this->questionCategoryRepository = new QuestionCategoryRepository();
        $this->questionCategoryTransformer = new QuestionCategoryTransformer();
    }

    public function getListData()
    {
        $data_input = $this->data;
        $keyword_search = @$data_input['keyword_search'];
        $data_condition = [];
        if (!empty($data_input['moet_level'])) {
            $data_condition['moet_level'] = $data_input['moet_level'];
        }
        if (!empty($data_input['parent_id'])) {
            $data_condition['parent_id'] = $data_input['parent_id'];
        }
        if (!empty($keyword_search)) {
            $condition['keyword_search'] = $keyword_search;
        }
        $data_condition['moet_unit_id'] = $this->moetUnitId;
        $data_question = $this->questionCategoryRepository->getData($data_condition);
        if (count($data_question) <= 0) {
            $data_result = [];
        } else {
            $data_result = $this->questionCategoryTransformer->transformCollection($data_question->all());
        }
        return $this->respondSuccess($data_result);
    }

    public function detail()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['question_category_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $category_info = $this->questionCategoryRepository->find($data_input['question_category_id']);
        $data_result = [];
        if (!empty($category_info)) {
            $data_result = $this->questionCategoryTransformer->transform($category_info);
        }
        return $this->respondSuccess($data_result);
    }

    public function create()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['name']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $data_create = [];
        $data_create['code'] = $this->questionCategoryRepository->genCodeCategory();
        $data_create['name'] = $data_input['name'];
        $data_create['moet_level'] = MOET_UNIT_LEVEL_BO;
        $data_create['moet_unit_id'] = $this->moetUnitId;
        $data_create['parent_id'] = empty($data_input['parent_id']) ? 0 : $data_input['parent_id'];
        $data_create['sort_index'] = empty($data_input['sort_index']) ? 0 : $data_input['sort_index'];
        $data_create['status'] = $data_input['status'];
        $data_create['created_at'] = Carbon::now();
        $data_create['created_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $this->questionCategoryRepository->create($data_create);
        return $this->respondSuccess([]);
    }

    public function update()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['name', 'question_category_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $data_update = [];
        $data_update['name'] = $data_input['name'];
        $data_update['sort_index'] = $data_input['sort_index'];
        $data_update['status'] = $data_input['status'];
        $data_update['updated_at'] = Carbon::now();
        $data_update['modified_user_id'] = Auth::check() ? Auth::user()->id : 0;
        $data_result = $this->questionCategoryRepository->update($data_input, $data_input['question_category_id']);
        if (!empty($data_result))
            return $this->respondSuccess([]);
        else
            return $this->responseError(trans('api.admin.fail'), []);
    }

    public function delete()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['question_category_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $this->questionCategoryRepository->delete($data_input['question_category_id']);
        return $this->respondSuccess([]);
    }
}

