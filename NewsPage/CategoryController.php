<?php
/**
 *File name : NewsController.php / Date: 2/28/2022 - 11:15 PM
 *Code Owner: Thanhnt/ Email: Thanhnt@omt.com.vn/ Phone: 0384428234
 */


namespace App\Http\Controllers\NewsPage;


use App\Http\Controllers\Controller;
use App\Repositories\NewsPage\CategoryRepository;

class CategoryController extends Controller
{

    protected $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new CategoryRepository();
    }

    public function load()
    {
        return $this->respondSuccess($this->repository->getAll($this->data));
    }

    public function create()
    {
        if ($this->validateParameterKeys(['type'])) {
            return $this->responseMissingParameters();
        }
        return $this->respondSuccess($this->repository->getDataCreate());
    }

    public function store()
    {
        if (!$this->validateParameterKeys(['name', 'offset'])) {
            return $this->responseMissingParameters();
        }
        return $this->respondSuccess($this->repository->storeCategory($this->moetUnitId, $this->data));
    }

    public function update()
    {
        if (!$this->validateParameterKeys(['category_id', 'name'])) {
            return $this->responseMissingParameters();
        }
        $result = $this->repository->update($this->data, $this->data['category_id']);
        return $result ? $this->respondSuccess() : $this->responseNotFoundOjbect();
    }

    public function updateOffset()
    {
        
    }

    public function destroy()
    {
        if (!$this->validateParameterKeys(['category_id'])) {
            return $this->responseMissingParameters();
        }
        $result = $this->repository->delete($this->data['category_id']);
        return $result ? $this->respondSuccess() : $this->responseNotFoundOjbect();
    }

}
