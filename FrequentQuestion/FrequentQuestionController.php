<?php

namespace App\Http\Controllers\FrequentQuestion;

use App\Helpers\Transformer\FrequentQuestion\FrequentQuestionTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\FrequentQuestion\FrequentQuestionRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FrequentQuestionController extends Controller
{
    private $frequent_question_repository;
    private $frequent_question_tranformer;

    public function __construct()
    {
        parent::__construct();
        $this->frequent_question_repository = new FrequentQuestionRepository();
        $this->frequent_question_tranformer = new FrequentQuestionTransformer();
    }

    public function getListData()
    {
        $data_input = $this->data;
        $keyword_search = @$data_input['keyword_search'];
        $data_condition['keyword_search'] = $keyword_search;

        $data_frequent = $this->frequent_question_repository->getListData($keyword_search);
        if (empty($data_frequent)){
            $data_result = [];
        }else{
            $data_result = $this->frequent_question_tranformer->transformCollect($data_frequent);
        }
        return $this->respondSuccess($data_result);
    }

    public function detail()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['frequent_question_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $frequent_question_info = $this->frequent_question_repository->find($data_input['frequent_question_id']);
        $data_result = [];
        if (!empty($frequent_question_info)) {
            $data_result = $this->frequent_question_tranformer->transform($frequent_question_info);
        }
        return $this->respondSuccess($data_result);
    }



    public function create()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['content','description', 'tenant_id']);
        if (!empty($validate)) {
            return $this->responseError(trans('api.param_error'), $validate);
        }
        $data_create = [];
        $data_create['content'] = $data_input['content'];
        $data_create['description'] = $data_input['description'];
        $data_create['tenant_id'] = $data_input['tenant_id'];
        $data_create['created_at'] = Carbon::now();
        if (!empty($data_input['tags'])){
            $data_create['tags'] = $data_input['tags'];
        }
        if (!empty($data_input['scope'])){
            $data_create['scope'] = $data_input['scope'];
        }
        $this->frequent_question_repository->create($data_create);
        return $this->respondSuccess([]);
    }

    public function update()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['content','description', 'tenant_id','frequent_question_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $data_update = [];
        $data_update['content'] = $data_input['content'];
        $data_update['description'] = $data_input['description'];
        $data_update['tenant_id'] = $data_input['tenant_id'];
        $data_update['updated_at'] = Carbon::now();
        $data_update['tags'] = @$data_input['tags'];
        $data_update['scope'] = @$data_input['scope'];
        $data_result = $this->frequent_question_repository->update($data_input, $data_input['frequent_question_id']);
        if (!empty($data_result))
            return $this->respondSuccess([]);
        else
            return $this->responseError(trans('api.admin.fail'), []);
    }

    public function delete()
    {
        $data_input = $this->data;
        $validate = $this->validateEmptyData($data_input, ['frequent_question_id']);
        if (!empty($validate)) {
            return $this->responseError($validate, trans('api.param_error'));
        }
        $this->frequent_question_repository->delete($data_input['frequent_question_id']);
        return $this->respondSuccess([]);
    }
}
