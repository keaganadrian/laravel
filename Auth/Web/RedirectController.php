<?php
/**
 *File name : RedirectController.php / Date: 11/30/2021 - 2:00 PM
 *Code Owner: Thanhnt/ Email: Thanhnt@omt.com.vn/ Phone: 0384428234
 */

namespace App\Http\Controllers\Auth\Web;


use App\Entities\MoetUnitEntity;
use App\Entities\UserEntity;
use App\Exceptions\ApiException;
use App\Http\Controllers\ResponseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;


class RedirectController
{

    use ResponseController;

    protected $userEntity;
    protected $moetLevel;
    protected $status;
    protected $data;
    protected $moetUnitEntity;

    public function __construct()
    {
        $this->status         = Config::get('error_status');
        $this->data           = array_merge(\request()->header(), \request()->json()->all());
        $this->moetUnitEntity = new MoetUnitEntity();
        $this->moetLevel      = request()->header('moetLevel');

        if (!isset($this->moetLevel)) {
            throw new ApiException($this->responseMissingParameters());
        }
        $this->userEntity = new UserEntity();
    }

    public function redirect()
    {
        $url = $this->getUrlRedirect();
        return $this->respondSuccess([
            'url' => $url,
        ]);
    }

    private function getUrlRedirect()
    {
        $user       = Auth::user();
        $moetUniIds = $user->roles()->get()->pluck('moet_unit_id')->toArray();
        $moetUnits  = $this->moetUnitEntity->getListMoetUnitByIds($moetUniIds);
        if (!$moetUnits->contains('moet_level', $this->moetLevel)) {
            return route(403);
        }
        switch ($this->moetLevel) {
            case MOET_UNIT_LEVEL_BO:
                return route('dashboard.index');
            case MOET_UNIT_LEVEL_DEPARTMENT;
            case MOET_UNIT_LEVEL_DIVISION;
            case MOET_UNIT_LEVEL_SCHOOL;
                if (!$moetUnits->contains('id', $this->data['moetUnitId'])) {
                    return route(403);
                }
                return route('dashboard.index');
            default:
                return route(403);
        }
    }

}
