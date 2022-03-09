<?php


namespace App\Http\Controllers\Student;


use App\Entities\MoetUnitEntity;
use App\Helpers\Transformer\Classes\ClassesTransformer;
use App\Helpers\Transformer\Grade\GradeTransformer;
use App\Helpers\Transformer\MoetUnitTransformer;
use App\Helpers\Transformer\StudentTransformer;
use App\Http\Controllers\Controller;
use App\Libs\CommonLib;
use App\Libs\Imports\ImportExcelLib;
use App\Repositories\Classes\ClassRepository;
use App\Repositories\Grade\GradeRepository;
use App\Repositories\Student\StudentRepository;
use App\Repositories\User\UserRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{

    private $student_repository;
    private $student_transformer;
    private $moet_unit_transformer;
    private $grade_repository;
    private $grade_transformer;
    private $user_repository;
    private $class_repository;
    private $class_transformer;

    private $default_password = "123456";

    public function __construct()
    {
        parent::__construct();
        $this->student_repository = new StudentRepository();
        $this->student_transformer = new StudentTransformer();
        $this->moet_unit_transformer = new MoetUnitTransformer();
        $this->grade_repository = new GradeRepository();
        $this->grade_transformer = new GradeTransformer();
        $this->user_repository = new UserRepository();
        $this->class_repository = new ClassRepository();
        $this->class_transformer = new ClassesTransformer();
    }

    public function index(Request $request)
    {
        list($departments, $divisions, $schools) = (new MoetUnitEntity())->getDepartmentDivisionSchoolByMoetUnit($this->moetUnit);
        $grades = $this->grade_repository->getGradesByMoetUnit($this->moetUnit);
        $classes = $this->class_repository->getClassesByMoetUnit($this->moetUnit);

        $students = $this->student_repository->getStudentPagination($this->data, ['user', 'bo', 'department', 'division', 'school', 'grade', 'thisClass']);
        return $this->respondSuccess([
            'departments' => $this->moet_unit_transformer->transformCollection($departments),
            'divisions' => $this->moet_unit_transformer->transformCollection($divisions),
            'schools' => $this->moet_unit_transformer->transformCollection($schools),
            'grades' => $this->grade_transformer->transformCollection($grades),
            'classes' => $this->class_transformer->transformCollection($classes),
            'students' => $students
        ]);

    }

    public function create(Request $request)
    {
        $data = $this->data;
        $data_insert = [];
        $this->makeDataCreateStudent($data_insert, $data);

        $validated = $this->validateDataCreateStudent($data_insert);
        if ($validated['status'] == false) {
            return $this->responseError($validated['message']);
        }

        $this->student_repository->createStudents($data_insert);

        return $this->respondSuccess(['message' => 'Tạo mới học sinh thành công']);
    }

    public function update(Request $request)
    {
        $data = $this->data;
        $student_id = $data['student_id'] ?? 0;
        $student_info = $this->student_repository->getData(['id' => $student_id], ['user'])->first();
        if (empty($student_info) || empty($student_info->user)) {
            return $this->responseError('Không tìm thấy thông tin học sinh');
        }

        $parameter_update_user = [
            'email'         => $data['email'] ?? "",
            'phone'         => $data['phone'] ?? "",
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'sis_id'        => $data['date_of_birth'] ?? null,
            'gender'        => $data['gender'] ?? null,
            'is_active'     => $data['is_active'] ?? null,
            'full_name'     => $data['full_name'] ?? null,
            'password'      => $data['password'] ?? null,
        ];

        $parameter_update_student = [
            "department_id" => $data['department_id'] ?? $student_info->department_id,
            "division_id"   => $data['division_id'] ?? $student_info->division_id,
            "school_id"     => $data['school_id'] ?? $student_info->school_id,
            "grade_id"      => $data['grade_id'] ?? $student_info->grade_id,
            "class_id"      => $data['class_id'] ?? $student_info->class_id,
        ];

        $this->student_repository->update($parameter_update_student, $student_id);
        $this->user_repository->updateUserInfo($student_info->user, $parameter_update_user);

        return $this->respondSuccess(['message' => 'Cập nhật thông tin học sinh thành công']);
    }

    public function delete(Request $request)
    {
        $data = $this->data;
        $student_id = $data['student_id'] ?? 0;
        $student_info = $this->student_repository->getData(['id' => $student_id], ['user'])->first();
        if (empty($student_info) || empty($student_info->user)) {
            return $this->responseError('Không tìm thấy thông tin học sinh');
        }

        $this->student_repository->deleteStudent($student_info);

        return $this->respondSuccess(['message' => 'Xóa thông tin học sinh thành công']);
    }

    /**
     * @param $data -> mang 1 chieu
     */
    protected function makeDataCreateStudent(&$data_insert, $data)
    {
        $department_id = $data['department_id'] ?? 0;
        $department_info = (new MoetUnitEntity())->getMoetUnitInfo($department_id);
        $data_insert[] = [
            'bo_id'         => !empty($department_info) ? $department_info->parent_id : 0,
            'department_id' => $department_id,
            'division_id'   => $data['division_id'] ?? 0,
            'school_id'     => $data['school_id'] ?? 0,
            'code'          => $data['code'] ?? 0,
            'username'      => $data['username'] ?? "",
            'full_name'     => $data['full_name'] ?? "",
            'grade_id'      => $data['grade_id'] ?? 0,
            'class_id'      => $data['class_id'] ?? 0,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender'        => $data['gender'] ?? USER_GENDER_UNDEFINED,
            'email'         => $data['email'] ?? "",
            'phone'         => $data['phone'] ?? "",
            'status'        => $data['status'] ?? STUDENT_STATUS_DANG_HOC,
            'password'      => $data['password'],
            'sis_id'        => $data['sis_id'],
        ];
    }

    protected function validateDataCreateStudent(array $data_insert)
    {
        if (empty($data_insert)) {
            return ['status' => false, 'message' => 'Không tìm thấy dữ liệu thêm mới học sinh'];
        }

        try {
            foreach ($data_insert as $i => $parameter) {
                if (empty($parameter['bo_id'])) {
                    return $this->validateError('Không tìm thấy thông tin bộ');
                }
                if (empty($parameter['department_id'])) {
                    return $this->validateError('Không tìm thấy thông tin sở');
                }
                if (empty($parameter['division_id'])) {
                    return $this->validateError('Không tìm thấy thông tin phòng');
                }
                if (empty($parameter['school_id'])) {
                    return $this->validateError('Không tìm thấy thông tin trường');
                }
                if (empty($parameter['code'])) {
                    return $this->validateError('Thiếu thông tin mã học sinh');
                }
                if (empty($parameter['username'])) {
                    return $this->validateError('Thiếu thông tin tên đăng nhập');
                }
                if (empty($parameter['full_name'])) {
                    return $this->validateError('Thiếu thông tin tên học sinh');
                }
                if (empty($parameter['grade_id'])) {
                    return $this->validateError('Không tìm thấy thông tin khối');
                }
                if (empty($parameter['class_id'])) {
                    return $this->validateError('Không tìm thấy thông tin lớp');
                }
                if (empty($parameter['password'])) {
                    return $this->validateError('Vui lòng nhập mật khẩu');
                }
                if (empty($parameter['sis_id'])) {
                    return $this->validateError('Vui lòng nhập mã CSDL');
                }
            }

            $usernames = collect($data_insert)->pluck('username')->toArray();
            $sis_id = collect($data_insert)->pluck('sis_id')->toArray();
            $isset_username = $this->user_repository->getData(['username' => $usernames]);
            $isset_sis_id = $this->user_repository->getData(['sis_id' => $sis_id]);

            if (count($isset_username) > 0) {
                return $this->validateError('Tên đăng nhập đã tồn tại');
            }
            if (count($isset_sis_id) > 0) {
                return $this->validateError('Mã CSDL đã tồn tại');
            }

            return $this->validateSuccessfully();
        } catch (\Exception $exception) {
            return $this->validateError('Đã có lỗi xảy ra, dữ liệu không đúng định dạng');
        }
    }

    protected function validateDataCreateStudentImport(array $data_insert)
    {
        $status = true;
        if (empty($data_insert))
            return ['status' => false, 'message' => 'Không tìm thấy dữ liệu thêm mới học sinh', 'data_insert' => $data_insert];

        try {
            foreach ($data_insert as $i => $parameter) {
                if (empty($parameter['bo_id'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Không tìm thấy thông tin bộ', $i, $data_insert);
                }
                if (empty($parameter['department_id'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Không tìm thấy thông tin sở', $i, $data_insert);
                }
                if (empty($parameter['division_id'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Không tìm thấy thông tin phòng', $i, $data_insert);
                }
                if (empty($parameter['school_id'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Không tìm thấy thông tin trường', $i, $data_insert);
                }
                if (empty($parameter['code'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Thiếu thông tin mã học sinh', $i, $data_insert);
                }
                if (empty($parameter['username'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Thiếu thông tin tên đăng nhập', $i, $data_insert);
                }
                if (empty($parameter['full_name'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Thiếu thông tin tên học sinh', $i, $data_insert);
                }
                if (empty($parameter['grade_id'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Không tìm thấy thông tin khối', $i, $data_insert);
                }
                if (empty($parameter['class_id'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Không tìm thấy thông tin lớp', $i, $data_insert);
                }
                if (empty($parameter['password'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Vui lòng nhập mật khẩu', $i, $data_insert);
                }
                if (empty($parameter['sis_id'])) {
                    $status = false;
                    $this->appendErrorToDataInsert('Vui lòng nhập mã CSDL', $i, $data_insert);
                }
            }

            $usernames = collect($data_insert)->pluck('username')->toArray();
            $sis_id = collect($data_insert)->pluck('sis_id')->toArray();
            $isset_username = $this->user_repository->getData(['username' => $usernames]);
            $isset_sis_id = $this->user_repository->getData(['sis_id' => $sis_id]);

            foreach($data_insert as $i => $parameter) {
                $status = false;
                if (count($isset_username) > 0) {
                    foreach($isset_username as $i => $user) {
                        if($user->username == $parameter['username']) {
                            $this->appendErrorToDataInsert('Tên đăng nhập '. $parameter['username'] .' đã tồn tại', $i, $data_insert);
                        }
                    }
                }

                if (count($isset_sis_id) > 0) {
                    $status = false;
                    foreach($isset_sis_id as $i => $user) {
                        if($user->sis_id == $parameter['sis_id']) {
                            $this->appendErrorToDataInsert('Mã CSDL '. $parameter['sis_id'] .' đã tồn tại', $i, $data_insert);
                        }
                    }
                }
            }

            return [
                'status' => $status,
                'data_insert' => $data_insert
            ];
        } catch (\Exception $exception) {
            return $this->validateError('Đã có lỗi xảy ra, dữ liệu không đúng định dạng');
        }
    }

    private function appendErrorToDataInsert($message, $i, &$data_insert)
    {
        if(!isset($data_insert[$i]['error']))
            $data_insert[$i]['error'] = [];
        return $data_insert[$i]['error'][] = $message;
    }

    private function validateSuccessfullyImport($message = "", $data_insert)
    {
        return ['status' => true, 'message' => $message, 'data_insert' => $data_insert];
    }

    private function validateError($message)
    {
        return ['status' => false, 'message' => $message];
    }

    private function validateSuccessfully($message = "")
    {
        return ['status' => true, 'message' => $message];
    }

    public function importLoad()
    {
        $file_import = $this->data['file_import'] ?? null;
        if (empty($file_import)) {
            return $this->responseError('Không tìm thấy File import');
        }

        $file = base64_decode($file_import);
        $data_import = ImportExcelLib::saveExcelFromBase64($file, 'import_students');
        $data_import_items = $data_import['data'] ?? [];
        $data_insert = $this->_parseDataExcelToParameterStudent($data_import_items);

        session()->remove('import_students_' . Auth::user()->id);

        $validated = $this->validateDataCreateStudentImport($data_insert);
        if ($validated['status'] == false) {
            return $this->responseError("Đã có lỗi xảy ra", @$validated['data_insert']);
        }

        session()->push('import_students_' . Auth::user()->id, $data_insert);

        return $this->respondSuccess($data_insert);
    }

    public function importSave(Request $request)
    {
        $data_session = session()->get('import_students_' . Auth::user()->id);
        $data_insert = $data_session[0];

        $validated = $this->validateDataCreateStudent($data_insert);
        if ($validated['status'] == false) {
            return $this->responseError($validated['message']);
        }

        $this->student_repository->createStudents($data_insert);
        session()->remove('import_students_' . Auth::user()->id);

        return $this->respondSuccess(['message' => 'Import danh sách học sinh thành công']);
    }

    private function _parseDataExcelToParameterStudent($data_import_items)
    {
        list($departments, $divisions, $schools) = (new MoetUnitEntity())->getDepartmentDivisionSchoolByMoetUnit($this->moetUnit);
        $departments = $departments->keyBy('id');
        $divisions = $divisions->keyBy('id');
        $schools = $schools->keyBy('sis_id');
        $grades = $this->grade_repository->getGradesByMoetUnit($this->moetUnit)->keyBy('code');
        $classes = $this->class_repository->getClassesByMoetUnit($this->moetUnit)->keyBy('sis_id');
        $result = [];
        foreach ($data_import_items as $i => $import_item) {
            if ($i > 0) {
                $school_info = isset($schools[$import_item[2]]) ? $schools[$import_item[2]] : null;
                $division_info = !empty($divisions[$school_info->parent_id]) ? $divisions[$school_info->parent_id] : null;
                $department_info = !empty($departments[$division_info->parent_id]) ? $departments[$division_info->parent_id] : null;
                $result[] = [
                    'sis_id'                => isset($import_item[1]) ? $import_item[1] : null,
                    'bo_id'                 => !empty($department_info) ? $department_info->parent_id : null,
                    'department_id'         => !empty($department_info) ? $department_info->id : null,
                    'department_name'       => !empty($department_info) ? $department_info->name : null,
                    'division_id'           => !empty($division_info) ? $division_info->id : null,
                    'division_name'         => !empty($division_info) ? $division_info->name : null,
                    'school_id'             => !empty($school_info) ? $school_info->id : null,
                    'school_name'           => !empty($school_info) ? $school_info->name : null,
                    'grade_id'              => isset($grades[$import_item[3]]) ? $grades[$import_item[3]]->id : null,
                    'grade_name'            => isset($grades[$import_item[3]]) ? $grades[$import_item[3]]->name : null,
                    'class_id'              => isset($classes[$import_item[4]]) ? $classes[$import_item[4]]->id : null,
                    'class_name'            => isset($classes[$import_item[4]]) ? $classes[$import_item[4]]->name : null,
                    'code'                  => $import_item[5] ?? null,
                    'username'              => $import_item[7] ?? null,
                    'full_name'             => $import_item[6] ?? null,
                    'date_of_birth'         => !empty($import_item[10]) ? Carbon::createFromFormat(DISPLAY_DATE_FORMAT,
                        $import_item[10]) : null,
                    'date_of_birth_display' => !empty($import_item[10]) ? $import_item[10] : null,
                    'gender'                => isset($import_item[11]) ? CommonLib::getGenderByName($import_item[11]) : USER_GENDER_UNDEFINED,
                    'gender_name'           => isset($import_item[11]) ? $import_item[11] : USER_GENDER_UNDEFINED,
                    'email'                 => $import_item[8] ?? null,
                    'phone'                 => $import_item[9] ?? null,
                    'status'                => STUDENT_STATUS_DANG_HOC,
                    'password'              => $this->default_password,
                ];
            }
        }

        return $result;
    }
}
