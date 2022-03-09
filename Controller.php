<?php

namespace App\Http\Controllers;

use App\Entities\MoetUnitEntity;
use App\Exceptions\ApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{

    use ResponseController;

    protected $data;
    protected $status;
    protected $moetUnitId;
    protected $moetUnitLevel;
    protected $moetUnit;
    protected $fieldSearch = [];

    public function __construct()
    {
        $this->status = Config::get('error_status');
        $this->data = \request()->json()->all();

//        if (!count($this->data)) {
//            throw new ApiException($this->responseMissingParameters());
//        }
        $lang = request()->header('lang');
        app('translator')->setLocale($lang);

        $this->moetUnitId = $this->getMoetUnitIdByData();
        $this->moetUnit = (new MoetUnitEntity())->getMoetUnitInfo($this->moetUnitId);
        if (is_null($this->moetUnit)) {
            throw new ApiException($this->responseError(trans($this->status['not_found']['moet_unit'])));
        }
    }

    public function getMoetUnitIdByData()
    {
        return !is_null(request()->header('moetUnitId')) && trim(request()->header('moetUnitId')) != '' ?
            (int)request()->header('moetUnitId') : 0;
    }

    public function validateParameterKeys($paramKeys, $data = null)
    {
        $validate = true;
        $data = is_null($data) ? $this->data : $data;
        foreach ($paramKeys as $key) {
            if (!isset($data[$key])) {
                $validate = false;
                break;
            }
        }

        return $validate;
    }

    public function validateEmptyData(array $data, array $param_needed_validate)
    {
        $error = [];
        foreach ($param_needed_validate as $param) {
            if (empty($data[$param])) {
                $error[] = trans('api.param_requered', ['param' => $param]);
            }
        }
        return $error;
    }

    public function getObjectsWithConditions($model, $whereQueryFunction = null, $relation = []){

        $query = $model;

        if(isset($this->data['fields'])){
            foreach($this->data['fields'] as $field => $values){
                $values = is_array($values) ? $values : [$values];
                $query = $query->whereIn($field, $values);
            }
        }

        if (isset($this->data['keyword_search'])){
            foreach ($this->fieldSearch as $index => $fieldSearch) {
                $query = $query->where($fieldSearch,'LIKE', "%{$this->data['keyword_search']}%");
            }
        }

        if(!is_null($whereQueryFunction)){
            $query = $query->where($whereQueryFunction);
        }
        if(isset($this->data['limit'])){
            $limit = $this->data['limit'];
            $page = isset($this->data['page']) ? $this->data['page'] : 1;
            return $query->paginate($limit, ['*'], 'page', $page);
        }

        return $query->get();


    }


}
