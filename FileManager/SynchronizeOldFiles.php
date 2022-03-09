<?php

namespace App\Http\Controllers\FileManager;

use App\Models\FileManager\Folder;
use App\Repositories\FileManager\FolderRepository;
use Config;
use File;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

class SynchronizeOldFiles extends Controller
{
    protected $photos_url;
    protected $files_url;

    protected $folder_repository;

    public function __construct()
    {
        $this->middleware('superAdmin');
        $this->photos_url = 'public/filemanager/photos';
        $this->files_url = 'public/filemanager/files';

        $this->folder_repository = new FolderRepository();
    }

    public function index(){
        ini_set('max_execution_time', 2400);

//        $photos_user_ids = $this->getUserIds(1);
//
//        $files_user_ids = $this->getUserIds(2);
//
//
//        foreach($photos_user_ids as $user_id){
//            $this->makeFoldersByUser($user_id,1);
//            $this->makeDBFromFiles($user_id,1);
//        }
//
//        foreach($files_user_ids as $user_id){
//            $this->makeFoldersByUser($user_id,2);
//            $this->makeDBFromFiles($user_id,2);
//        }

    }

    private function getUserIds($type){
        $default_path = $type == 1 ? $this->photos_url : $this->files_url;
        $sub_folders = scandir($default_path);

        $user_ids = array();
        foreach($sub_folders as $sub_folder){

            if(in_array($sub_folder,['.','..']))
                continue;

            if(is_numeric($sub_folder))
                array_push($user_ids,$sub_folder);
        }

        return $user_ids;
    }

    public function makeDBFromFiles($user_id, $type){
        $folders = Folder::where('user_id',$user_id)
            ->whereType($type)
            ->with('files')
            ->get();


        foreach($folders as $folder){

            if($folder->parent_id == 0){
                $folder_path = str_replace("KO_file_manager", "filemanager", $folder->path);
                $folder_path = str_replace("KO_photos", "photos", $folder_path);
                $folder_path = str_replace("KO_files", "files", $folder_path);
            }else{
                $folder_path = $folder->path;
            }


            if($folder_path[0] == '/')
                $folder_path = substr($folder_path,1);

            if(!File::exists($folder_path))
                continue;

            $files = dir($folder_path);
            $data_insert = array();
            while (($file = $files->read()) !== false) {
                if ($file == "." || $file == "..") {
                    continue;
                }

                $real_file = $folder_path . "/" . $file;

                if(!is_dir($real_file) &&
                    (   containString($real_file,[
                        '.gif',
                        '.jpg',
                        '.jpeg',
                        '.png',
                        '.GIF',
                        '.JPG',
                        '.JPEG',
                        '.PNG',
                        '.pdf',
                        '.doc',
                        '.docx',
                        '.xls',
                        '.xlsx',
                        '.ppt',
                        '.pptx',
                        '.PDF',
                        '.DOC',
                        '.DOCX',
                        '.XLS',
                        '.XLSX',
                        '.PPT',
                        '.PPTX',
                        ]) )
                ){

                    $extension = pathinfo($real_file, PATHINFO_EXTENSION);
                    $content_type = mime_content_type($real_file);

                    array_push($data_insert, [
                        'folder_id'  => $folder->id,
                        'user_id'    => $user_id,
                        'soft_delete'=> 0,
                        'type'       => $type,
                        'code_url'   => $folder->code_url,
                        'path'       => $real_file,
                        'name'       => $file,
                        'extension'  => $extension,
                        'content_type' => $content_type
                    ]);
                }

            }
            \App\Models\FileManager\File::insert($data_insert);
            $folder->update(['synchronize' => 1]);
        }
    }

    public function makeFoldersByUser($user_id, $type){
        $default_path = $type == 1 ? $this->photos_url : $this->files_url;
        $default_path = $default_path.'/'.$user_id;
        $default_folder = $this->folder_repository->getDefaultFolder($user_id,$type);
        $this->synchronizeFolder($default_path,$type,$default_folder->id,$user_id);
    }

    public function synchronizeFolder($folder_path,$type,$parent_folder_id,$user_id){

        if(!File::exists($folder_path))
            return false;

        $sub_folders = scandir($folder_path);
        foreach($sub_folders as $sub_folder){
            if($sub_folder == 'thumbs')
                continue;
            if(in_array($sub_folder,['.','..']))
                continue;
            $name = $sub_folder;
            $sub_folder = $folder_path.'/'.$sub_folder;


            if (!is_dir($sub_folder))
                continue;

            $folder = Folder::create([
                'user_id'   => $user_id,
                'name'      => $name,
                'path'      => $sub_folder,
                'parent_id' => $parent_folder_id,
                'type'      => $type,
                'code_url'  => 0
            ]);


//            echo $sub_folder.'<br/>';

            $this->synchronizeFolder($sub_folder,$type,$folder->id,$user_id);

        }
    }
}
