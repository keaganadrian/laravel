<?php
/**
 *File name : BaseImport.php  / Date: 12/15/2021 - 2:05 PM
 *Code Owner: Dao Thi Minh Nguyet / Phone: 0985455294 / Email: nguyetdtm@omt.vn
 */

namespace App\Http\Controllers\Excel;


use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToArray;

class BaseImport implements ToArray
{

    use Importable;

    protected $index_header_row;
    protected $index_header_col;
    protected $data;

    public function __construct($index_header_row = 1, $index_header_col = 0)
    {
        $this->index_header_row = $index_header_row;
        $this->index_header_col = $index_header_col;
    }

    public function array(array $rows)
    {
        $data_items = array();

        if ($this->index_header_row < 2) {
            $this->data = 1;

            return $rows;
        } else {
            $header      = [];
            $header_code = $rows[0];
            $header_name = $rows[1];
            unset($rows[0]);
            unset($rows[1]);
            foreach ($header_code as $key => $code) {
                $header[$code] = isset($header_name[$key]) ? $header_name[$key] : "";
            }
            $data_items[] = $header;
            foreach ($rows as $row_data) {
                $data = [];
                foreach ($row_data as $key => $row) {

                    if ($key >= $this->index_header_col) {
                        if (!empty($header_code[$key])) {
                            $data[$header_code[$key]] = $row;
                        }
                    }
                }
                $data_items[] = $data;
            }

            $this->data = $data_items;

            return $this->data;
        }
    }

    public function handleReturn()
    {
        return $this->data;
    }
}