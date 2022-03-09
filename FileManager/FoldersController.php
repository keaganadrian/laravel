<?php

namespace App\Http\Controllers\FileManager;

use App\Http\Controllers\TeacherController;
use App\Repositories\FileManager\FileCacheRepository;
use App\Repositories\FileManager\FileRepository;
use App\Repositories\FileManager\FolderRepository;
use App\Repositories\FileServer\FileServerRepository;
use Illuminate\Support\Facades\Auth;

class FoldersController extends TeacherController
{

    protected $folderRepository;

    public function __construct()
    {
        parent::__construct();
        $this->folderRepository = new FolderRepository();
    }

    public function load(){

        $fileType = isset($this->data['type']) ? $this->data['type'] : 1;
        $folderId = isset($this->data['folder_id']) ? $this->data['folder_id'] : 0;

        $mainFolder = $this->folderRepository->getMainFoldersWithChildrenByType(Auth::user(), $fileType, $folderId);

        if(is_null($mainFolder)){
            return $this->responseError( trans('file_manager.not_found_folder'));
        }

        $folderWithParents = $this->folderRepository->getFolderWithParents($mainFolder);
        $files = $mainFolder->files;

        //update list cache files
        $fileIds = $files->pluck('id')->toArray();
        $fileCacheRepository = new FileCacheRepository();
        $fileCacheRepository->saveCacheMultipleFiles($fileIds);

        return $this->respondSuccess([
            'folder' => $this->transformFolder($mainFolder),
            'parents_folder_links' => $folderWithParents->map(function($folder){
                return $this->transformFolder($folder);
            })->toArray(),
            'children_folders' => $mainFolder->children->map(function($folder){
                return $this->transformFolder($folder);
            })->toArray(),
            'files' => $files->map(function($file){
                return [
                    'id'    => $file->id,
                    'type'  => $file->type,
                    'name'  => $file->name,
                    'path'  => $file->path,
                    'extension' => $file->extension,
                    'content_type' => $file->content_type
                ];
            })
        ]);

    }

    private function transformFolder($folder){
        return [
            'id'    => $folder->id,
            'name'  => $folder->name,
            'type'  => $folder->type,
            'can_delete' => $folder->canDelete() ? 1 : 0
        ];
    }

    public function create(){

        $paramKeys = ['parent_id','name'];
        if (!$this->validateParameterKeys($paramKeys)) {
            return $this->responseMissingParameters();
        }

        $parentFolder = $this->folderRepository->getParentFolder(Auth::user(), $this->data['parent_id'], true);
        if(is_null($parentFolder)){
            return $this->responseError(trans('file_manager.not_found_folder'));
        }

        $folderName = standardString($this->data['name']);

        // validate duplicate name
        $exist_folders = $parentFolder->children->filter(function($folder) use ($folderName){
            return standardString($folder->name) === $folderName;
        });

        if(count($exist_folders) > 0){
            return $this->responseError(trans('file_manager.exist_folder_name'));
        }

        $folder = $this->folderRepository->createFolderByParentFolder(Auth::user(),$parentFolder,$folderName);
        return $this->respondSuccess($this->transformFolder($folder));
    }

    public function update(){
        $paramKeys = ['folder_id','name'];
        if (!$this->validateParameterKeys($paramKeys)) {
            return $this->responseMissingParameters();
        }

        $folder = $this->folderRepository->getParentFolder(Auth::user(), $this->data['folder_id'], true);
        if(is_null($folder)){
            return $this->responseError( trans('file_manager.not_found_folder'));
        }

        if($this->folderRepository->checkExistNameFolder($folder,$this->data['name'])){
            return $this->responseError( trans('file_manager.exist_folder_name'));
        }

        $folder->update(array(
            'name'  => $this->data['name']
        ));

        $folder->recordLog('RENAME');
        return $this->respondSuccess($this->transformFolder($folder));
    }

    public function delete(){
        $paramKeys = ['folder_id'];
        if (!$this->validateParameterKeys($paramKeys)) {
            return $this->responseMissingParameters();
        }

        $folder = $this->folderRepository->getParentFolder(Auth::user(), $this->data['folder_id'], true);
        if(is_null($folder)){
            return $this->responseError( trans('file_manager.not_found_folder'));
        }

        if(!$folder->canDelete()){
            return $this->responseError(trans('file_manager.can_not_delete_folder'));
        }

        $folder->update(array(
            'soft_delete'  => 1
        ));

        $folder->recordLog('DELETED');

        return $this->respondSuccess(trans('file_manager.delete_success'));
    }

    public function upload(){

        $paramKeys = ['type','folder_id','file'];
        if (!$this->validateParameterKeys($paramKeys)) {
            return $this->responseMissingParameters();
        }

        $folder = $this->folderRepository->getParentFolder(Auth::user(), $this->data['folder_id'], true);
        if(is_null($folder)){
            return $this->responseError( trans('file_manager.not_found_folder'));
        }


        $fileRepository = new FileRepository();
        $file = $fileRepository->saveDatabaseFileAndUpload($folder,$this->data['type'], $this->data['file']);

        if(!$file){
            return $this->responseError( trans('file_manager.can_not_upload_file'));
        }

        return $this->respondSuccess([
            'id'    => $file->id,
            'type'  => $file->type,
            'name'  => $file->name,
            'path'  => $file->path,
            'extension' => $file->extension,
            'content_type' => $file->content_type
        ]);
    }

}
