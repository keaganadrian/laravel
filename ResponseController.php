<?php
/**
 *File name : ResponeController.php / Date: 2/15/2022 - 11:05 PM
 *Code Owner: Thanhnt/ Email: Thanhnt@omt.com.vn/ Phone: 0384428234
 */

namespace App\Http\Controllers;


trait ResponseController
{

    public function responseMissingParameters()
    {
        return $this->responseError(trans($this->status['missing_parameters']));
    }

    public function responseNotFoundOjbect()
    {
        return $this->responseError(trans(trans($this->status['not_found']['object'])));
    }

    public function responeException()
    {
        return $this->responseError(trans($this->status['exception']));
    }

    public function responseNotFoundClass()
    {
        return $this->responseError(trans($this->status['not_found']['class']));
    }

    public function responseNotFoundSchool()
    {
        return $this->responseError(trans($this->status['not_found']['school']));
    }

    public function responseNotFoundStudent()
    {
        return $this->responseError(trans($this->status['not_found']['student']));
    }

    public function responseUnauthozied()
    {
        return $this->responseError(trans($this->status['unauthorized']));
    }

    public function isValidTimestamp(
        $timestamp
    ) {
        return ((string)(int)$timestamp === $timestamp)
            && ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }

    public function respondSuccess(
        $data = []
    ) {
        return \response()->json([
            'status' => STATUS_SUCCESS,
            'data'   => $data,
        ]);
    }

    public function responseError(
        $message,
        $data = ""
    ) {
        return \response()->json([
            'status'  => STATUS_ERROR,
            'message' => trans($message),
            'data'    => $data,
        ]);
    }

    public function respond(
        $message,
        $data = ""
    ) {
        return \response()->json([
            'status'  => STATUS_ERROR,
            'message' => $message,
            'data'    => $data,
        ]);
    }

}