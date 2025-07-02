<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;

class AuthController extends Controller
{
    /**
     * Đăng nhập cho khách hàng hoặc nhân viên
     * Nếu tìm thấy trong bảng khách_hang thì trả về thông tin khách hàng
     * Nếu không, tìm trong bảng nhan_vien, nếu đúng trả về thông tin nhân viên
     */
    public function login(Request $request)
    {
        $request->validate([
            'sodienthoai' => 'required',
            'password' => 'required',
        ]);

        $sodienthoai = trim(strip_tags($request->sodienthoai));
        $password = $request->password;

        // 1. Kiểm tra khách hàng
        $user = DB::table('khach_hang')->where('sodienthoai', $sodienthoai)->first();

        if ($user) {
            if (!Hash::check($password, $user->password)) {
                return response()->json(['message' => 'Mật khẩu không đúng'], 401);
            }
            return response()->json([
                'role' => 'khach_hang',
                'id' => $user->id_kh,
                'ten' => $user->ten,
                'sodienthoai' => $user->sodienthoai,
                'diem' => $user->diem,
                'ngaysinh' => $user->ngaysinh,
                'gioitinh' => $user->gioitinh,
            ]);
        }

        // 2. Nếu không phải khách hàng, kiểm tra nhân viên
        $staff = DB::table('nhan_vien')->where('sodienthoai', $sodienthoai)->first();

        if ($staff) {
            if ($staff->trangthai == 0) {
                return response()->json(['message' => 'Tài khoản đã bị khóa'], 403);
            }
            if (!Hash::check($password, $staff->password)) {
                return response()->json(['message' => 'Mật khẩu không đúng'], 401);
            }
            $role = $staff->id_quyen == 1 ? 'admin' : 'nhan_vien';
            return response()->json([
                'role' => $role,
                'id' => $staff->id_nv,
                'ten' => $staff->ten,
                'sodienthoai' => $staff->sodienthoai,

                'id_quyen' => $staff->id_quyen,
                'ngaysinh' => $staff->ngaysinh,
                'gioitinh' => $staff->gioitinh,
            ]);
        }

        // 3. Không tìm thấy tài khoản
        return response()->json(['message' => 'Số điện thoại không tồn tại'], 404);
    }

    /**
     * Đăng ký khách hàng mới
     * Chỉ cho phép đăng ký với bảng khách_hang
     */
    public function register(Request $request)
    {
        $request->validate([
            'ten' => 'required|string|max:225',
            'sodienthoai' => 'required|string|max:12|unique:khach_hang,sodienthoai',
            'password' => 'required|string|min:6',
        ]);

        $ten = trim(strip_tags($request->ten));
        $sodienthoai = trim(strip_tags($request->sodienthoai));

        // // Kiểm tra số điện thoại đã tồn tại bên bảng nhan_vien chưa
        // $existsInNhanVien = DB::table('nhan_vien')->where('sodienthoai', $sodienthoai)->exists();
        // if ($existsInNhanVien) {
        //     return response()->json([
        //         'message' => 'Số điện thoại đã tồn tại'
        //     ], 422);
        // }

        $password = Hash::make($request->password);

        $id = DB::table('khach_hang')->insertGetId([
            'ten' => $ten,
            'sodienthoai' => $sodienthoai,
            'password' => $password,
            'id_quyen' => 3,
            'ngaysinh' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'id' => $id,
            'ten' => $ten,
            'sodienthoai' => $sodienthoai,
            'message' => 'Đăng ký thành công'
        ], 201);
    }
    //đăng nhập bằng Google
    public function loginGoogle(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'ten' => 'required|string',
            'sodienthoai' => 'nullable|string',
        ]);

        // Kiểm tra user đã tồn tại chưa
        $user = DB::table('khach_hang')->where('email', $request->email)->first();

        if ($user) {
            // Đã có user, trả về thông tin
            return response()->json([
                'role' => 'khach_hang',
                'id' => $user->id_kh,
                'ten' => $user->ten,
                'email' => $user->email,
                'sodienthoai' => $user->sodienthoai,
                'diem' => $user->diem,
                'ngaysinh' => $user->ngaysinh,
                'gioitinh' => $user->gioitinh,
            ]);
        } else {
            // Chưa có user, tạo mới
            $id = DB::table('khach_hang')->insertGetId([
                'ten' => $request->ten,
                'email' => $request->email,
                'sodienthoai' => $request->sodienthoai,
                'id_quyen' => 3,
                'ngaysinh' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json([
                'role' => 'khach_hang',
                'id' => $id,
                'ten' => $request->ten,
                'email' => $request->email,
                'sodienthoai' => $request->sodienthoai,
                'diem' => 0,
                'ngaysinh' => now(),
                'gioitinh' => null,
            ]);
        }
    }
    /**
     * Cập nhật thông tin cá nhân của khách hàng
     * Chỉ cho phép cập nhật thông tin của khách hàng đã đăng nhập
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'id_kh' => 'required|exists:khach_hang,id_kh',
            'ten' => 'nullable|string|max:225',
            'sodienthoai' => 'nullable|string|max:12',
            'email' => 'nullable|email',
            'diachi' => 'nullable|string|max:225',
            'ngaysinh' => 'nullable|date',
            'gioitinh' => 'nullable|in:Nam,Nữ',
            'password' => 'nullable|string|min:6',
        ]);

        $data = [];
        if ($request->filled('ten'))
            $data['ten'] = trim(strip_tags($request->ten));
        if ($request->filled('sodienthoai'))
            $data['sodienthoai'] = trim(strip_tags($request->sodienthoai));
        if ($request->filled('email'))
            $data['email'] = trim(strip_tags($request->email));
        if ($request->filled('diachi'))
            $data['diachi'] = trim(strip_tags($request->diachi));
        if ($request->filled('ngaysinh'))
            $data['ngaysinh'] = $request->ngaysinh;
        if ($request->filled('gioitinh'))
            $data['gioitinh'] = $request->gioitinh;
        if ($request->filled('password'))
            $data['password'] = \Hash::make($request->password);

        $data['updated_at'] = now();

        DB::table('khach_hang')->where('id_kh', $request->id_kh)->update($data);

        return response()->json([
            'message' => 'Cập nhật thông tin thành công',
            'data' => $data
        ]);
    }
    /**
     * Đổi mật khẩu cho khách hàng
     * Yêu cầu: id_kh, mật khẩu cũ, mật khẩu mới
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'id_kh' => 'required|exists:khach_hang,id_kh',
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        // Lấy thông tin khách hàng
        $user = DB::table('khach_hang')->where('id_kh', $request->id_kh)->first();

        // Kiểm tra mật khẩu cũ
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Mật khẩu cũ không đúng'], 401);
        }

        // Cập nhật mật khẩu mới
        DB::table('khach_hang')->where('id_kh', $request->id_kh)->update([
            'password' => Hash::make($request->new_password),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    }
    /**
     * Lấy thông tin khách hàng theo id_kh
     */
    public function getUser($id)
    {
        $user = DB::table('khach_hang')->where('id_kh', $id)->first();

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy khách hàng'], 404);
        }

        return response()->json([
            'id' => $user->id_kh,
            'ten' => $user->ten,
            'sodienthoai' => $user->sodienthoai,
            'email' => $user->email,
            'diachi' => $user->diachi,
            'diem' => $user->diem,
            'ngaysinh' => $user->ngaysinh,
            'gioitinh' => $user->gioitinh,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }
    /**
     * Đổi mật khẩu cho khách hàng theo số điện thoại
     * Yêu cầu: sodienthoai, mật khẩu cũ, mật khẩu mới
     */
    public function changePasswordByPhone(Request $request)
    {
        $request->validate([
            'sodienthoai' => 'required',
            'new_password' => 'required|string|min:6',
        ]);

        // Lấy thông tin khách hàng theo số điện thoại
        $user = DB::table('khach_hang')->where('sodienthoai', $request->sodienthoai)->first();

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy khách hàng với số điện thoại này'], 404);
        }

        // Cập nhật mật khẩu mới
        DB::table('khach_hang')->where('sodienthoai', $request->sodienthoai)->update([
            'password' => Hash::make($request->new_password),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    }
    //Thêm nhân viên mới
    public function addStaff(Request $request)
    {
        $request->validate([
            'ten' => 'required|string|max:225',
            'sodienthoai' => 'required|string|max:12|unique:nhan_vien,sodienthoai',

            'password' => 'required|string|min:6',
            'ngaysinh' => 'nullable|date',
            'gioitinh' => 'nullable|in:Nam,Nữ',
            'diachi' => 'nullable|string|max:225',
        ]);

        // Kiểm tra số điện thoại đã tồn tại ở bảng khách_hang chưa
        $existsInKhachHang = DB::table('khach_hang')->where('sodienthoai', $request->sodienthoai)->exists();
        if ($existsInKhachHang) {
            return response()->json([
                'message' => 'Số điện thoại đã tồn tại trong hệ thống'
            ], 422);
        }

        $id = DB::table('nhan_vien')->insertGetId([
            'ten' => trim(strip_tags($request->ten)),
            'sodienthoai' => trim(strip_tags($request->sodienthoai)),

            'password' => Hash::make($request->password),
            'id_quyen' => 2,
            'ngaysinh' => $request->ngaysinh,
            'gioitinh' => $request->gioitinh,
            'diachi' => $request->diachi,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'id' => $id,
            'message' => 'Thêm nhân viên thành công'
        ], 201);
    }
    // Cập nhật thông tin nhân viên
    public function updateStaff(Request $request)
    {
        $request->validate([
            'id_nv' => 'required|exists:nhan_vien,id_nv',
            'ten' => 'nullable|string|max:225',
            'sodienthoai' => 'nullable|string|max:12',

            'ngaysinh' => 'nullable|date',
            'gioitinh' => 'nullable|in:Nam,Nữ',
            'password' => 'nullable|string|min:6',
            'diachi' => 'nullable|string|max:225',
        ]);

        // Nếu có sửa số điện thoại thì kiểm tra trùng
        if ($request->filled('sodienthoai')) {
            $existsInNhanVien = DB::table('nhan_vien')
                ->where('sodienthoai', $request->sodienthoai)
                ->where('id_nv', '!=', $request->id_nv)
                ->exists();
            $existsInKhachHang = DB::table('khach_hang')
                ->where('sodienthoai', $request->sodienthoai)
                ->exists();
            if ($existsInNhanVien || $existsInKhachHang) {
                return response()->json([
                    'message' => 'Số điện thoại đã tồn tại trong hệ thống'
                ], 422);
            }
        }

        $data = [];
        if ($request->filled('ten'))
            $data['ten'] = trim(strip_tags($request->ten));
        if ($request->filled('sodienthoai'))
            $data['sodienthoai'] = trim(strip_tags($request->sodienthoai));

        if ($request->filled('ngaysinh'))
            $data['ngaysinh'] = $request->ngaysinh;
        if ($request->filled('gioitinh'))
            $data['gioitinh'] = $request->gioitinh;
        if ($request->filled('password'))
            $data['password'] = Hash::make($request->password);
        if ($request->filled('diachi'))
            $data['diachi'] = trim(strip_tags($request->diachi));

        $data['updated_at'] = now();

        DB::table('nhan_vien')->where('id_nv', $request->id_nv)->update($data);

        return response()->json([
            'message' => 'Cập nhật nhân viên thành công',
            'data' => $data
        ]);
    }
    /**
     * Lấy thông tin nhân viên theo id_nv
     */
    public function getStaff($id)
    {
        $staff = DB::table('nhan_vien')->where('id_nv', $id)->first();

        if (!$staff) {
            return response()->json(['message' => 'Không tìm thấy nhân viên'], 404);
        }

        return response()->json([
            'id' => $staff->id_nv,
            'ten' => $staff->ten,
            'sodienthoai' => $staff->sodienthoai,

            'diachi' => $staff->diachi,
            'id_quyen' => $staff->id_quyen,
            'ngaysinh' => $staff->ngaysinh,
            'gioitinh' => $staff->gioitinh,
            'created_at' => $staff->created_at,
            'updated_at' => $staff->updated_at,
        ]);
    }
    /**
     * Lấy danh sách tất cả nhân viên
     */
    public function getAllStaff()
    {
        // Lấy tất cả nhân viên, loại bỏ admin (id_quyen = 1)
        $staffs = DB::table('nhan_vien')
            ->where('id_quyen', '!=', 1)
            ->get();

        return response()->json($staffs);
    }
    // API ẩn nhân viên
    public function hideStaff(Request $request)
    {
        $request->validate([
            'id_nv' => 'required|exists:nhan_vien,id_nv',
        ]);

        DB::table('nhan_vien')->where('id_nv', $request->id_nv)->update([
            'trangthai' => 0,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Ẩn nhân viên thành công']);
    }
    // API mở lại tài khoản nhân viên
    public function unhideStaff(Request $request)
    {
        $request->validate([
            'id_nv' => 'required|exists:nhan_vien,id_nv',
        ]);

        DB::table('nhan_vien')->where('id_nv', $request->id_nv)->update([
            'trangthai' => 1,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Mở lại tài khoản nhân viên thành công']);
    }

    //  Lấy thông tin nhân viên theo id_nv
    public function getNhanVienById($id_nv)
    {
        $staff = DB::table('nhan_vien')->where('id_nv', $id_nv)->first();

        if (!$staff) {
            return response()->json(['message' => 'Không tìm thấy nhân viên'], 404);
        }

        return response()->json([
            'id_nv' => $staff->id_nv,
            'ten' => $staff->ten,
            'sodienthoai' => $staff->sodienthoai,
            'diachi' => $staff->diachi,
            'id_quyen' => $staff->id_quyen,
            'ngaysinh' => $staff->ngaysinh,
            'gioitinh' => $staff->gioitinh,
            'trangthai' => $staff->trangthai,
            'created_at' => $staff->created_at,
            'updated_at' => $staff->updated_at,
        ]);
    }
}
