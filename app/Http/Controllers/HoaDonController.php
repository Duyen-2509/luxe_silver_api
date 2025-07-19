<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HoaDonController extends Controller
{
    // Lấy danh sách hóa đơn (có trạng thái)
    public function getHoaDon()
    {
        $hoadons = DB::table('hoadon')
            ->leftJoin('trangthai_dh', 'hoadon.id_ttdh', '=', 'trangthai_dh.id_ttdh')
            ->leftJoin('nhan_vien', 'hoadon.id_nv', '=', 'nhan_vien.id_nv')
            ->select(
                'hoadon.*',
                'trangthai_dh.ten as ten_trangthai',
                'nhan_vien.ten as ten_nhanvien'
            )
            ->orderByDesc('hoadon.ngaylap')
            ->get();

        return response()->json(['hoadon' => $hoadons]);
    }

    // Lấy chi tiết hóa đơn theo mã hóa đơn
    public function getChiTietHoaDon($mahd)
    {
        $chitiets = DB::table('chitiet_hd')
            ->where('chitiet_hd.mahd', $mahd)
            ->leftJoin('sanpham', 'chitiet_hd.id_sp', '=', 'sanpham.id_sp')
            ->leftJoin('hinhanh', 'chitiet_hd.id_sp', '=', 'hinhanh.id_sp')
            ->select(
                'chitiet_hd.*',
                'sanpham.tensp',
                DB::raw('MIN(hinhanh.duong_dan) as image')
            )
            ->groupBy(
                'chitiet_hd.id_cthd',
                'chitiet_hd.mahd',
                'chitiet_hd.id_sp',
                'chitiet_hd.id_ctsp',
                'chitiet_hd.soluong',
                'chitiet_hd.gia',
                'chitiet_hd.created_at',
                'chitiet_hd.trangthai',
                'chitiet_hd.updated_at',
                'sanpham.tensp'
            )
            ->get();

        // Gán lại image_url cho từng chi tiết
        foreach ($chitiets as $ct) {
            $ct->image_url = $ct->image ? url('uploads/' . $ct->image) : null;
        }

        $hoadon = DB::table('hoadon')->where('mahd', $mahd)->first();

        return response()->json([
            'chitiet_hd' => $chitiets,
            'hoadon' => $hoadon,
        ]);
    }

    // Lấy danh sách trạng thái hóa đơn
    public function getTrangThaiHD()
    {
        $trangthais = DB::table('trangthai_dh')->get();
        return response()->json(['trangthai_hd' => $trangthais]);
    }

    // Thêm mới hóa đơn và chi tiết hóa đơn
    public function addHoaDon(Request $request)
    {
        $request->validate([
            'id_kh' => 'required|integer|exists:khach_hang,id_kh',
            'id_nv' => 'nullable|integer|exists:nhan_vien,id_nv',
            'tong_gia_sp' => 'required|integer|min:0',
            'tonggia' => 'required|integer|min:0',
            'tien_ship' => 'required|integer|min:0',
            'id_voucher' => 'nullable|integer|exists:voucher,id_voucher',
            'diachi' => 'required|string|max:255',
            'phuongthuc_thanhtoan' => 'required|string|max:255',
            'chitiet' => 'required|array|min:1',
            'chitiet.*.id_sp' => 'required|integer|exists:sanpham,id_sp',
            'chitiet.*.id_ctsp' => 'required|integer|exists:chitiet_sp,id_ctsp',
            'chitiet.*.soluong' => 'required|integer|min:1',
            'chitiet.*.gia' => 'required|integer|min:0',
        ]);

        $mahd = 'HD' . time();

        DB::table('hoadon')->insert([
            'mahd' => $mahd,
            'id_nv' => $request->filled('id_nv') ? $request->id_nv : null,
            'id_kh' => $request->id_kh,
            'id_ttdh' => 1, // Chờ xác nhận
            'tong_gia_sp' => $request->tong_gia_sp,
            'tien_ship' => $request->tien_ship,
            'id_voucher' => $request->filled('id_voucher') ? $request->id_voucher : null,
            'tonggia' => $request->tonggia,
            'diachi' => $request->diachi,
            'phuongthuc_thanhtoan' => $request->phuongthuc_thanhtoan,
            'ngaylap' => now(),
            'trangthai' => 1,
            'updated_at' => now(),
            'ly_do_kh' => null,
            'ly_do_nv' => null,
        ]);

        foreach ($request->chitiet as $ct) {
            DB::table('chitiet_hd')->insert([
                'mahd' => $mahd,
                'id_sp' => $ct['id_sp'],
                'id_ctsp' => $ct['id_ctsp'],
                'soluong' => $ct['soluong'],
                'gia' => $ct['gia'],
                'created_at' => now(),
                'trangthai' => 1,
                'updated_at' => now(),
            ]);
            // Trừ kho và tăng lượt bán ngay khi đặt hàng
            DB::table('chitiet_sp')
                ->where('id_ctsp', $ct['id_ctsp'])
                ->decrement('soluong_kho', $ct['soluong']);
            DB::table('chitiet_sp')
                ->where('id_ctsp', $ct['id_ctsp'])
                ->increment('soluong_daban', $ct['soluong']);
            // Trừ số lượng voucher nếu có sử dụng
            if ($request->filled('id_voucher')) {
                DB::table('voucher')
                    ->where('id_voucher', $request->id_voucher)
                    ->where('soluong', '>', 0)
                    ->decrement('soluong', 1);
            }
            // Sau khi insert hóa đơn và chi tiết hóa đơn xong:
            if ($request->has('used_points') && $request->used_points > 0) {
                DB::table('khach_hang')
                    ->where('id_kh', $request->id_kh)
                    ->decrement('diem', $request->used_points);
                DB::table('hoadon')
                    ->where('mahd', $mahd)
                    ->update(['diem_sudung' => $request->used_points]);
            }

        }
        // Gửi thông báo cho tất cả nhân viên (đơn hàng mới)
        $nhanViens = DB::table('nhan_vien')->pluck('id_nv');
        foreach ($nhanViens as $id_nv) {
            DB::table('thong_bao')->insert([
                'tieu_de' => 'Đơn hàng mới',
                'noi_dung' => 'Khách hàng vừa đặt đơn hàng #' . $mahd,
                'id_loai_tb' => 1, // 1 = Đơn hàng mới
                'id_kh' => $request->id_kh,
                'id_nv' => $id_nv,
                'mahd' => $mahd,
                'created_at' => now(),
            ]);
        }
        // Gửi thông báo cho khách (chờ xác nhận)
        DB::table('thong_bao')->insert([
            'tieu_de' => 'Đặt hàng thành công',
            'noi_dung' => 'Đơn hàng #' . $mahd . ' của bạn đã được đặt thành công và đang chờ xác nhận.',
            'id_loai_tb' => 2, // 2 = Cập nhật trạng thái đơn hàng
            'id_kh' => $request->id_kh,
            'id_nv' => null,
            'mahd' => $mahd,
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Tạo hóa đơn thành công', 'mahd' => $mahd]);
    }

    // Nhân viên bấm "Đã giao cho khách"
    public function daGiaoHang(Request $request, $mahd)
    {
        $data = [
            'id_ttdh' => 3,
            'trangthai' => 1, // Đang giao, trạng thái 1
            'updated_at' => now(),
        ];
        if ($request->filled('id_nv')) {
            $data['id_nv'] = $request->id_nv;
        }
        DB::table('hoadon')->where('mahd', $mahd)->update($data);
        DB::table('chitiet_hd')->where('mahd', $mahd)->update([
            'trangthai' => 1, // Đang giao, trạng thái 1
            'updated_at' => now(),
        ]);
        $hoadon = DB::table('hoadon')->where('mahd', $mahd)->first();
        if ($hoadon && $hoadon->id_kh) {
            DB::table('thong_bao')->insert([
                'tieu_de' => 'Cập nhật trạng thái đơn hàng',
                'noi_dung' => 'Đơn hàng #' . $mahd . ' đã được giao cho đơn vị vận chuyển.',
                'id_loai_tb' => 2, // 2 = Cập nhật trạng thái đơn hàng
                'id_kh' => $hoadon->id_kh,
                'mahd' => $mahd,
                'created_at' => now(),
            ]);
        }
        return response()->json(['message' => 'Đã cập nhật trạng thái ĐANG GIAO cho đơn hàng']);
    }

    // Nhân viên bấm "Đã giao tới khách"
    public function daGiaoToi(Request $request, $mahd)
    {
        $data = [
            'id_ttdh' => 3,
            'trangthai' => 2, // Đang giao, trạng thái 2
            'updated_at' => now(),
        ];
        if ($request->filled('id_nv')) {
            $data['id_nv'] = $request->id_nv;
        }
        DB::table('hoadon')->where('mahd', $mahd)->update($data);
        DB::table('chitiet_hd')->where('mahd', $mahd)->update([
            'trangthai' => 2, // Đang giao, trạng thái 2
            'updated_at' => now(),
        ]);
        $hoadon = DB::table('hoadon')->where('mahd', $mahd)->first();
        if ($hoadon && $hoadon->id_kh) {
            DB::table('thong_bao')->insert([
                'tieu_de' => 'Cập nhật trạng thái đơn hàng',
                'noi_dung' => 'Đơn hàng #' . $mahd . ' đã được giao tới khách.',
                'id_loai_tb' => 2, // 2 = Cập nhật trạng thái đơn hàng
                'id_kh' => $hoadon->id_kh,
                'mahd' => $mahd,
                'created_at' => now(),
            ]);
        }
        return response()->json(['message' => 'Đã cập nhật trạng thái ĐÃ GIAO TỚI cho đơn hàng']);
    }

    // Khách bấm "Đã nhận hàng"
    public function daNhanHang(Request $request, $mahd)
    {
        $data = [
            'id_ttdh' => 4,
            'trangthai' => 1,
            'updated_at' => now(),
        ];
        if ($request->filled('id_nv')) {
            $data['id_nv'] = $request->id_nv;
        }
        DB::table('hoadon')->where('mahd', $mahd)->update($data);
        DB::table('chitiet_hd')->where('mahd', $mahd)->update([
            'trangthai' => 1,
            'updated_at' => now(),
        ]);
        // Cộng điểm cho khách hàng
        $hoadon = DB::table('hoadon')->where('mahd', $mahd)->first();
        if ($hoadon && $hoadon->id_kh) {
            $diemCong = round($hoadon->tong_gia_sp * 0.005);
            DB::table('khach_hang')
                ->where('id_kh', $hoadon->id_kh)
                ->increment('diem', $diemCong);
            DB::table('thong_bao')->insert([
                'tieu_de' => 'Cập nhật trạng thái đơn hàng',
                'noi_dung' => 'Đơn hàng #' . $mahd . ' đã được xác nhận ĐÃ NHẬN.',
                'id_loai_tb' => 2,
                'id_kh' => $hoadon->id_kh,
                'id_nv' => null,
                'mahd' => $mahd,
                'created_at' => now(),
            ]);
        }
        return response()->json(['message' => 'Đã cập nhật trạng thái ĐÃ NHẬN cho đơn hàng']);
    }

    // Khách bấm "Trả hàng" (yêu cầu trả hàng)
    public function traHang(Request $request, $mahd)
    {
        $request->validate([
            'ly_do_kh' => 'required|string|max:500'
        ]);

        $order = DB::table('hoadon')->where('mahd', $mahd)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        $currentTime = now();
        $data = [
            'id_ttdh' => 6,
            'trangthai' => 3,
            'updated_at' => $currentTime,
            'ly_do_kh' => $request->ly_do_kh,
        ];
        if ($request->filled('id_nv')) {
            $data['id_nv'] = $request->id_nv;
        }
        DB::table('hoadon')->where('mahd', $mahd)->update($data);
        DB::table('chitiet_hd')->where('mahd', $mahd)->update([
            'trangthai' => 3,
            'updated_at' => $currentTime,
        ]);
        $nhanViens = DB::table('nhan_vien')->pluck('id_nv');
        foreach ($nhanViens as $id_nv) {
            DB::table('thong_bao')->insert([
                'tieu_de' => 'Khách hàng yêu cầu trả hàng',
                'noi_dung' => 'Khách hàng đã gửi yêu cầu trả hàng cho đơn #' . $mahd . '. Lý do: ' . $request->ly_do_kh,
                'id_loai_tb' => 7, // 7 = Khách trả hàng
                'id_nv' => $id_nv,
                'id_kh' => $order->id_kh,
                'mahd' => $mahd,
                'ly_do' => $request->ly_do_kh,
                'created_at' => now(),
            ]);
        }
        return response()->json(['success' => true, 'message' => 'Đã gửi yêu cầu trả hàng thành công. Vui lòng chờ nhân viên xử lý.']);
    }

    // Khách hủy đơn (chỉ khi chưa giao)
    public function huyDon(Request $request, $mahd)
    {
        $request->validate([
            'ly_do_kh' => 'required|string|max:500'
        ]);

        $order = DB::table('hoadon')->where('mahd', $mahd)->first();
        if ($order && $order->diem_sudung > 0) {
            DB::table('khach_hang')
                ->where('id_kh', $order->id_kh)
                ->increment('diem', $order->diem_sudung);
            DB::table('hoadon')
                ->where('mahd', $mahd)
                ->update(['diem_sudung' => 0]);
        }
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        if (!in_array($order->id_ttdh, [1, 2])) {
            return response()->json(['success' => false, 'message' => 'Chỉ có thể hủy đơn khi chưa giao'], 400);
        }

        $currentTime = now();
        $data = [
            'id_ttdh' => 5,
            'trangthai' => 1,
            'updated_at' => $currentTime,
            'ly_do_kh' => $request->ly_do_kh,
        ];
        if ($request->filled('id_nv')) {
            $data['id_nv'] = $request->id_nv;
        }
        DB::table('hoadon')->where('mahd', $mahd)->update($data);
        DB::table('chitiet_hd')->where('mahd', $mahd)->update([
            'trangthai' => 1,
            'updated_at' => $currentTime,
        ]);
        $nhanViens = DB::table('nhan_vien')->pluck('id_nv');
        foreach ($nhanViens as $id_nv) {
            DB::table('thong_bao')->insert([
                'tieu_de' => 'Khách hàng hủy đơn',
                'noi_dung' => 'Khách hàng đã hủy đơn hàng #' . $mahd . '. Lý do: ' . $request->ly_do_kh,
                'id_loai_tb' => 5, // 5 = Khách hủy đơn
                'id_nv' => $id_nv,
                'id_kh' => $order->id_kh,
                'mahd' => $mahd,
                'ly_do' => $request->ly_do_kh,
                'created_at' => now(),
            ]);
        }
        return response()->json(['success' => true, 'message' => 'Đã hủy đơn hàng']);
    }

    // Nhân viên duyệt trả hàng

    public function duyetTraHang(Request $request, $mahd)
    {
        $request->validate([
            'pheduyet' => 'required|boolean', // true: duyệt, false: không duyệt
            'ly_do_nv' => 'nullable|string|max:500',
            'id_nv' => 'required|integer|exists:nhan_vien,id_nv'
        ]);

        $order = DB::table('hoadon')->where('mahd', $mahd)->first();
        if ($order && $order->diem_sudung > 0) {
            DB::table('khach_hang')
                ->where('id_kh', $order->id_kh)
                ->increment('diem', $order->diem_sudung);
            DB::table('hoadon')
                ->where('mahd', $mahd)
                ->update(['diem_sudung' => 0]);
        }
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }
        if ($order->id_ttdh != 6 || $order->trangthai != 3) {
            return response()->json(['success' => false, 'message' => 'Đơn hàng không ở trạng thái chờ duyệt trả hàng'], 400);
        }

        $currentTime = now();

        if ($request->pheduyet) {
            // Duyệt thành công
            $data = [
                'id_ttdh' => 6,
                'trangthai' => 1,
                'updated_at' => $currentTime,
                'ly_do_nv' => null,
                'id_nv' => $request->id_nv,
            ];
            DB::table('hoadon')->where('mahd', $mahd)->update($data);
            DB::table('chitiet_hd')->where('mahd', $mahd)->update([
                'trangthai' => 1,
                'updated_at' => $currentTime,
            ]);
            DB::table('thong_bao')->insert([
                'tieu_de' => 'Duyệt trả hàng',
                'noi_dung' => 'Đơn hàng #' . $mahd . ' đã được duyệt trả hàng.',
                'id_loai_tb' => 8, // 8 = Duyệt trả hàng
                'id_kh' => $order->id_kh,
                'id_nv' => $request->id_nv,
                'mahd' => $mahd,
                'created_at' => now(),
            ]);
            return response()->json(['success' => true, 'message' => 'Đã duyệt trả hàng thành công. Số lượng sản phẩm đã được hoàn trả về kho.']);
        } else {
            // Không duyệt
            $data = [
                'id_ttdh' => 4,
                'trangthai' => 1,
                'updated_at' => $currentTime,
                'ly_do_nv' => $request->ly_do_nv,
                'id_nv' => $request->id_nv,
            ];
            DB::table('hoadon')->where('mahd', $mahd)->update($data);
            DB::table('chitiet_hd')->where('mahd', $mahd)->update([
                'trangthai' => 1,
                'updated_at' => $currentTime,
            ]);
            DB::table('thong_bao')->insert([
                'tieu_de' => 'Không duyệt trả hàng',
                'noi_dung' => 'Đơn hàng #' . $mahd . ' không được duyệt trả hàng. Lý do: ' . $request->ly_do_nv,
                'id_loai_tb' => 9, // 9 = Từ chối trả hàng
                'id_kh' => $order->id_kh,
                'id_nv' => $request->id_nv,
                'mahd' => $mahd,
                'ly_do' => $request->ly_do_nv,
                'created_at' => now(),
            ]);
            return response()->json(['success' => true, 'message' => 'Đã từ chối yêu cầu trả hàng. Đơn hàng chuyển về trạng thái "Đã nhận".']);
        }
    }
    // Nhân viên hủy đơn
    public function huyDonNV(Request $request, $mahd)
    {
        $request->validate([
            'ly_do_nv' => 'required|string|max:500'
        ]);

        $order = DB::table('hoadon')->where('mahd', $mahd)->first();
        if ($order && $order->diem_sudung > 0) {
            DB::table('khach_hang')
                ->where('id_kh', $order->id_kh)
                ->increment('diem', $order->diem_sudung);
            DB::table('hoadon')
                ->where('mahd', $mahd)
                ->update(['diem_sudung' => 0]);
        }
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        if (!in_array($order->id_ttdh, [1, 2])) {
            return response()->json(['success' => false, 'message' => 'Chỉ có thể hủy đơn khi chờ xử lý hoặc đang xử lý'], 400);
        }

        $currentTime = now();
        $data = [
            'id_ttdh' => 5,
            'trangthai' => 1,
            'updated_at' => $currentTime,
            'ly_do_nv' => $request->ly_do_nv,
        ];
        if ($request->filled('id_nv')) {
            $data['id_nv'] = $request->id_nv;
        }
        DB::table('hoadon')->where('mahd', $mahd)->update($data);
        DB::table('chitiet_hd')->where('mahd', $mahd)->update([
            'trangthai' => 1,
            'updated_at' => $currentTime,
        ]);
        DB::table('thong_bao')->insert([
            'tieu_de' => 'Đơn hàng bị hủy',
            'noi_dung' => 'Đơn hàng #' . $mahd . ' đã bị nhân viên hủy. Lý do: ' . $request->ly_do_nv,
            'id_loai_tb' => 6, // 6 = Nhân viên hủy đơn
            'id_kh' => $order->id_kh,
            'id_nv' => $request->id_nv,
            'mahd' => $mahd,
            'ly_do' => $request->ly_do_nv,
            'created_at' => now(),
        ]);
        return response()->json(['success' => true, 'message' => 'Nhân viên đã hủy đơn hàng']);
    }
    // Gán hoặc cập nhật nhân viên xử lý cho đơn hàng
    public function ganNhanVien(Request $request, $mahd)
    {
        $request->validate([
            'id_nv' => 'required|integer|exists:nhan_vien,id_nv'
        ]);

        $order = DB::table('hoadon')->where('mahd', $mahd)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        DB::table('hoadon')->where('mahd', $mahd)->update([
            'id_nv' => $request->id_nv,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Đã gán nhân viên xử lý cho đơn hàng']);
    }
    // Nhân viên cập nhật trạng thái "Đang xử lý" cho đơn hàng
    public function dangXuLy(Request $request, $mahd)
    {
        $affected = DB::table('hoadon')
            ->where('mahd', $mahd)
            ->update([
                'id_ttdh' => 2,
                'updated_at' => now(),
            ]);
        if ($affected) {
            return response()->json(['message' => 'Cập nhật trạng thái Đang xử lý thành công']);
        } else {
            return response()->json(['message' => 'Không tìm thấy hóa đơn hoặc không có thay đổi'], 404);
        }
    }
    // API thu hồi hàng (nhân viên chủ động thu hồi)
    public function thuHoiHang(Request $request, $mahd)
    {
        $request->validate([
            'ly_do_nv' => 'required|string|max:500',
            'id_nv' => 'required|integer|exists:nhan_vien,id_nv'
        ]);

        $order = DB::table('hoadon')->where('mahd', $mahd)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        $currentTime = now();

        $data = [
            'id_ttdh' => 6,
            'trangthai' => 1,
            'updated_at' => $currentTime,
            'ly_do_nv' => $request->ly_do_nv,
            'id_nv' => $request->id_nv,
        ];
        DB::table('hoadon')->where('mahd', $mahd)->update($data);
        DB::table('chitiet_hd')->where('mahd', $mahd)->update([
            'trangthai' => 1,
            'updated_at' => $currentTime,
        ]);


        return response()->json(['success' => true, 'message' => 'Đã thu hồi hàng và chuyển đơn sang trạng thái trả hàng thành công']);
    }
    public function xuLyDonQuaHan()
    {
        $now = now();
        // Lấy các đơn đang ở trạng thái ĐANG GIAO (id_ttdh = 3, trangthai = 2)
        $orders = DB::table('hoadon')
            ->where('id_ttdh', 3)
            ->where('trangthai', 2)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            // Nếu đã quá 7 ngày kể từ updated_at
            //if ($now->diffInDays($order->updated_at) > 7)
            $updatedAt = \Carbon\Carbon::parse($order->updated_at);
            $diff = $updatedAt->diffInMinutes($now);
            Log::info('XuLyDonQuaHan', [
                'now' => $now,
                'updated_at' => $updatedAt,
                'diff' => $diff,
                'mahd' => $order->mahd
            ]);
            // test 1p
            if ($diff > 1) {
                DB::table('hoadon')
                    ->where('mahd', $order->mahd)
                    ->update([
                        'id_ttdh' => 4, // Đã nhận hàng
                        'trangthai' => 1,
                        'updated_at' => $now,
                    ]);
                DB::table('chitiet_hd')
                    ->where('mahd', $order->mahd)
                    ->update([
                        'trangthai' => 1,
                        'updated_at' => $now,
                    ]);
                // Cộng điểm cho khách hàng
                if ($order->id_kh) {
                    $diemCong = round($order->tong_gia_sp * 0.005);
                    DB::table('khach_hang')
                        ->where('id_kh', $order->id_kh)
                        ->increment('diem', $diemCong);
                }
                $count++;
            }
        }
        return response()->json(['message' => "Đã tự động cập nhật $count đơn hàng quá hạn sang trạng thái ĐÃ NHẬN"]);
    }
}
