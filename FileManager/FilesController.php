<?php

namespace App\Http\Controllers\FileManager;

use App\Http\Controllers\TeacherController;
use App\Repositories\FileManager\FileRepository;
use Illuminate\Support\Facades\Auth;

class FilesController extends TeacherController
{
    protected $file_repository;
    protected $no_photo_path;
    public function __construct()
    {
        parent::__construct();
        $this->file_repository = new FileRepository();
        $this->no_photo_path = '/public/photos/noimage.png';
    }

    public function update(){
        $paramKeys = ['file_id','name'];
        if (!$this->validateParameterKeys($paramKeys)) {
            return $this->responseMissingParameters();
        }

        $file = $this->file_repository->getFileFromDB(Auth::user()->id, $this->data['file_id']);
        if(is_null($file)){
            return $this->responseError( trans('file_manager.not_found_file'));
        }

        $file = $this->file_repository->updateFile($file,$this->data);
        return $this->respondSuccess([
            'id'    => $file->id,
            'type'  => $file->type,
            'name'  => $file->name,
            'path'  => $file->path,
            'extension' => $file->extension,
            'content_type' => $file->content_type
        ]);
    }

    public function delete(){
        $paramKeys = ['file_id'];
        if (!$this->validateParameterKeys($paramKeys)) {
            return $this->responseMissingParameters();
        }

        $file = $this->file_repository->getFileFromDB(Auth::user()->id, $this->data['file_id']);
        if(is_null($file)){
            return $this->responseError( trans('file_manager.not_found_file'));
        }

        $this->file_repository->deleteFile($file);
        return $this->respondSuccess();

    }
}
