<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HoaDonController extends Controller
{
    //Lấy danh sách hóa đơn (có trạng thái)

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
    //Lấy chi tiết hóa đơn theo mã hóa đơn

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
                'sanpham.tensp'
            )
            ->get();

        // Thêm url đầy đủ cho ảnh
        foreach ($chitiets as $ct) {
            $ct->image_url = $ct->image ? url('uploads/' . $ct->image) : null;
        }

        return response()->json(['chitiet_hd' => $chitiets]);
    }
    //Lấy danh sách trạng thái hóa đơn
    public function getTrangThaiHD()
    {
        $trangthais = DB::table('trangthai_dh')->get();
        return response()->json(['trangthai_hd' => $trangthais]);
    }
    //Thêm mới hóa đơn và chi tiết hóa đơn
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
            'chitiet.*.soluong' => 'required|integer|min:1',
            'chitiet.*.gia' => 'required|integer|min:0',
        ]);

        $mahd = 'HD' . time();

        $id_hoadon = DB::table('hoadon')->insertGetId([
            'mahd' => $mahd,
            'id_nv' => $request->filled('id_nv') ? $request->id_nv : null,
            'id_kh' => $request->id_kh,
            'id_ttdh' => 1, // trạng thái mặc định (ví dụ: chờ xác nhận)
            'tong_gia_sp' => $request->tong_gia_sp,
            'tien_ship' => $request->tien_ship,
            'id_voucher' => $request->filled('id_voucher') ? $request->id_voucher : null,
            'tonggia' => $request->tonggia,
            'diachi' => $request->diachi,
            'phuongthuc_thanhtoan' => $request->phuongthuc_thanhtoan,
            'ngaylap' => now(),
        ]);

        foreach ($request->chitiet as $ct) {
            DB::table('chitiet_hd')->insert([
                'mahd' => $mahd,
                'id_sp' => $ct['id_sp'],
                'id_ctsp' => $ct['id_ctsp'],
                'soluong' => $ct['soluong'],
                'gia' => $ct['gia'],
                'created_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Tạo hóa đơn thành công', 'mahd' => $mahd]);
    }
    // API thay đổi trạng thái giao hàng của hóa đơn
    public function updateTrangThai(Request $request, $mahd)
    {
        $request->validate([
            'id_ttdh' => 'required|integer|exists:trangthai_dh,id_ttdh',
            'id_nv' => 'nullable|integer|exists:nhan_vien,id_nv',
        ]);

        $data = ['id_ttdh' => $request->id_ttdh];
        if ($request->filled('id_nv')) {
            $data['id_nv'] = $request->id_nv;
        }

        $affected = DB::table('hoadon')
            ->where('mahd', $mahd)
            ->update($data);

        if ($affected) {
            return response()->json(['message' => 'Cập nhật trạng thái thành công']);
        } else {
            return response()->json(['message' => 'Không tìm thấy hóa đơn hoặc trạng thái không thay đổi'], 404);
        }
    }
    //API thay đổi id_nv trong hóa đơn
    public function updateNhanVien(Request $request, $mahd)
    {
        $request->validate([
            'id_nv' => 'required|integer|exists:nhan_vien,id_nv',
        ]);

        $affected = DB::table('hoadon')
            ->where('mahd', $mahd)
            ->update(['id_nv' => $request->id_nv]);

        if ($affected) {
            return response()->json(['message' => 'Cập nhật nhân viên thành công']);
        } else {
            return response()->json(['message' => 'Không tìm thấy hóa đơn hoặc nhân viên không thay đổi'], 404);
        }
    }
}
