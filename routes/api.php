<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\HoaDonController;
use App\Http\Controllers\BinhLuanController;
use App\Http\Controllers\ThongKeController;
use App\Http\Controllers\TienShipController;

// User info (auth)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login-google', [AuthController::class, 'loginGoogle']);
Route::post('/update-profile', [AuthController::class, 'updateProfile']);
Route::post('/change-password', [AuthController::class, 'changePassword']);
Route::get('/get-user/{id}', [AuthController::class, 'getUser']);
Route::get('/nhan-vien/{id_nv}', [AuthController::class, 'getNhanVienById']);
Route::post('/change-password-by-phone', [AuthController::class, 'changePasswordByPhone']);

// Staff
Route::post('/staff/add', [AuthController::class, 'addStaff']);
Route::post('/staff/update', [AuthController::class, 'updateStaff']);
Route::get('/get-staff/{id}', [AuthController::class, 'getStaff']);
Route::get('/staff', [AuthController::class, 'getAllStaff']);
Route::post('/staff/hide', [AuthController::class, 'hideStaff']);
Route::post('/staff/unhide', [AuthController::class, 'unhideStaff']);

// Product
Route::post('/add-product', [ProductController::class, 'addProduct']);
Route::get('/products', [ProductController::class, 'getProducts']);
Route::get('/products/{id}', [ProductController::class, 'getProductDetail']);
Route::get('/dat-rieng/{id}', [ProductController::class, 'getDatRieng']);
Route::put('/dat-rieng/{id}', [ProductController::class, 'updateDatRieng']);
Route::put('/products/{id}', [ProductController::class, 'updateProduct']);
Route::put('/products/{id}/hide', [ProductController::class, 'hideProduct']);
Route::put('/products/{id}/show', [ProductController::class, 'showProduct']);
Route::put('/products/{id}/stock', [ProductController::class, 'updateStock']);

// Hóa đơn
Route::get('/hoadon', [HoaDonController::class, 'getHoaDon']);
Route::get('/hoadon/{mahd}/chitiet', [HoaDonController::class, 'getChiTietHoaDon']);
Route::get('/trangthai-hoadon', [HoaDonController::class, 'getTrangThaiHD']);
Route::post('/hoadon/add', [HoaDonController::class, 'addHoaDon']);
Route::post('/hoadon/{mahd}/da-giao', [HoaDonController::class, 'daGiaoHang']);
Route::post('/hoadon/{mahd}/da-nhan', [HoaDonController::class, 'daNhanHang']);
Route::post('/hoadon/{mahd}/tra-hang', [HoaDonController::class, 'traHang']);
Route::post('/hoadon/{mahd}/huy', [HoaDonController::class, 'huyDon']);
Route::post('/hoadon/{mahd}/huy-nv', [HoaDonController::class, 'huyDonNV']);
Route::post('/hoadon/xu-ly-don-qua-han', [HoaDonController::class, 'xuLyDonQuaHan']);
Route::get('/hoadon/sap-het-han', [HoaDonController::class, 'getDonSapHetHan']);
Route::get('/hoadon/cho-tra-hang', [HoaDonController::class, 'getDonChoTraHang']);
Route::get('/hoadon/{mahd}/kiem-tra-tra-hang', [HoaDonController::class, 'kiemTraTraHang']);
Route::post('/hoadon/{mahd}/duyet-tra-hang', [HoaDonController::class, 'duyetTraHang']);
Route::post('/hoadon/{mahd}/gan-nhan-vien', [HoaDonController::class, 'ganNhanVien']);
Route::post('/hoadon/{mahd}/dang-xu-ly', [HoaDonController::class, 'dangXuLy']);
Route::post('/hoadon/{mahd}/thu-hoi-hang', [HoaDonController::class, 'thuHoiHang']);
Route::post('/hoadon/xu-ly-don-qua-han', [HoaDonController::class, 'xuLyDonQuaHan']);
// Voucher
Route::post('/voucher', [VoucherController::class, 'addVoucher']);
Route::put('/voucher/{id}', [VoucherController::class, 'updateVoucher']);
Route::get('/voucher', [VoucherController::class, 'getVoucher']);
Route::get('/loai-voucher', [VoucherController::class, 'getLoaiVoucher']);
Route::post('/voucher/hide/{id}', [VoucherController::class, 'hideVoucher']);
Route::post('/voucher/use/{id}', [VoucherController::class, 'useVoucher']);
Route::post('/voucher/show/{id}', [VoucherController::class, 'showVoucher']);

// Bình luận
Route::post('/binhluan', [BinhLuanController::class, 'danhGia']);
Route::put('/binhluan/{id_bl}', [BinhLuanController::class, 'suaDanhGia']);
Route::delete('/binhluan/{id_bl}', [BinhLuanController::class, 'xoaDanhGia']);
Route::post('/binhluan/{id_bl}/traloi', [BinhLuanController::class, 'traLoiBinhLuan']);
Route::delete('/traloi-binhluan/{id_ctbl}', [BinhLuanController::class, 'xoaTraLoiBinhLuan']);
Route::get('/binhluan/thongke/{id_sp}', [BinhLuanController::class, 'thongKeDanhGia']);
Route::get('/binhluan/sanpham/{id_sp}', [BinhLuanController::class, 'getBinhLuanSanPham']);

// Tính tiền ship

Route::get('/tien-ship', [TienShipController::class, 'getTienShip']);
Route::put('/tien-ship/{id}', [TienShipController::class, 'updateTienShip']);

// Thống kê
Route::get('/thongke', [ThongKeController::class, 'thongKe']);

// Thanh toán Stripe
Route::post('/stripe/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);
