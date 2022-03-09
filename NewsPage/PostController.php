<?php
/**
 *File name : NewsController.php / Date: 2/28/2022 - 11:15 PM
 *Code Owner: Thanhnt/ Email: Thanhnt@omt.com.vn/ Phone: 0384428234
 */

/**
 *File name : NewsController.php / Date: 2/28/2022 - 11:15 PM
 *Code Owner: Thanhnt/ Email: Thanhnt@omt.com.vn/ Phone: 0384428234
 */


namespace App\Http\Controllers\NewsPage;


use App\Helpers\Transformer\NewsPage\PostTransformer;
use App\Http\Controllers\Controller;
use App\Models\News\Post;
use App\Repositories\NewsPage\PostRepository;

class PostController extends Controller
{

    protected $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new PostRepository();
    }

    public function load()
    {
        return $this->respondSuccess($this->repository->getPosts($this->data));
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
        return $this->respondSuccess($this->repository->storePost($this->moetUnitId, $this->data));
    }

    public function update()
    {
        if (!$this->validateParameterKeys(['post_id', 'name'])) {
            return $this->responseMissingParameters();
        }
        $result = $this->repository->update($this->data, $this->data['post_id']);
        return $result ? $this->respondSuccess() : $this->responseNotFoundOjbect();
    }

    public function destroy()
    {
        if (!$this->validateParameterKeys(['post_id'])) {
            return $this->responseMissingParameters();
        }
        $result = $this->repository->delete($this->data['post_id']);
        return $result ? $this->respondSuccess() : $this->responseNotFoundOjbect();
    }

}
