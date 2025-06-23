<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\HoaDonController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API lấy danh sách khách hàng
//Route::get('/khachHang', [UserController::class, 'getKhachHang']);
//Đăng nhập
Route::post('/login', [AuthController::class, 'login']);
// Đăng ký
Route::post('/register', [AuthController::class, 'register']);
// Đăng nhập bằng Google
Route::post('/login-google', [AuthController::class, 'loginGoogle']);
//update thông tin người dùng
Route::post('/update-profile', [AuthController::class, 'updateProfile']);
// Đổi mật khẩu
Route::post('/change-password', [AuthController::class, 'changePassword']);
// Lấy thông tin người dùng theo ID (GET)
Route::get('/get-user/{id}', [AuthController::class, 'getUser']);
// Lấy thông tin người dùng theo số điện thoại (GET)
Route::post('/change-password-by-phone', [AuthController::class, 'changePasswordByPhone']);
// Lấy danh sách sản phẩm
Route::post('/add-product', [ProductController::class, 'addProduct']);
//Sản phẩm
Route::get('/products', [ProductController::class, 'getProducts']);
//Chi tiết sản phẩm
Route::get('/products/{id}', [ProductController::class, 'getProductDetail']);
// Lấy thông tin dat_rieng
Route::get('/dat-rieng/{id}', [ProductController::class, 'getDatRieng']);
// Cập nhật thông tin dat_rieng
Route::put('/dat-rieng/{id}', [ProductController::class, 'updateDatRieng']);
// thêm voucher
Route::post('/voucher', [VoucherController::class, 'addVoucher']);
// Cập nhật voucher
Route::put('/voucher/{id}', [VoucherController::class, 'updateVoucher']);
//Lấy danh sách voucher
Route::get('/voucher', [VoucherController::class, 'getVoucher']);
// Lấy danh sách loại voucher
Route::get('/loai-voucher', [VoucherController::class, 'getLoaiVoucher']);
//Thêm nhân viên
Route::post('/staff/add', [AuthController::class, 'addStaff']);
// Cập nhật thông tin nhân viên
Route::post('/staff/update', [AuthController::class, 'updateStaff']);
// Lấy thông tin nhân viên theo ID
Route::get('/get-staff/{id}', [AuthController::class, 'getStaff']);
// Lấy danh sách nhân viên
Route::get('/staff', [AuthController::class, 'getAllStaff']);
// Xóa nhân viên
Route::post('/staff/hide', [AuthController::class, 'hideStaff']);
// Hiện lại nhân viên đã xóa
Route::post('/staff/unhide', [AuthController::class, 'unhideStaff']);
// Lấy danh sách hóa đơn
Route::get('/hoadon', [HoaDonController::class, 'getHoaDon']);
// Lấy chi tiết hóa đơn theo mã hóa đơn
Route::get('/hoadon/{mahd}/chitiet', [HoaDonController::class, 'getChiTietHoaDon']);
// Lấy danh sách trạng thái hóa đơn
Route::get('/trangthai-hoadon', [HoaDonController::class, 'getTrangThaiHD']);
// Thêm mới hóa đơn và chi tiết hóa đơn
Route::post('/hoadon/add', [HoaDonController::class, 'addHoaDon']);
// Cập nhật trạng thái hóa đơn
Route::put('/hoadon/{mahd}/trangthai', [HoaDonController::class, 'updateTrangThai']);
// Cập nhật nhân viên xử lý hóa đơn
Route::put('/hoadon/{mahd}/nhanvien', [HoaDonController::class, 'updateNhanVien']);
// Cập nhật sản phẩm
Route::put('/products/{id}', [ProductController::class, 'updateProduct']);
// Ẩn sản phẩm
Route::put('/products/{id}/hide', [ProductController::class, 'hideProduct']);
// Hiện sản phẩm ẩn
Route::put('/products/{id}/show', [ProductController::class, 'showProduct']);
// Thang toán bằng Stripe
Route::post('/stripe/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);
