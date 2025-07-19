<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThongBaoController extends Controller
{
    // Lấy thông báo cho khách hàng
    public function getThongBaoKhach($id_kh)
    {
        $donHang = DB::table('thong_bao')
            ->where('id_kh', $id_kh)
            ->whereNull('id_nv') // CHỈ LẤY THÔNG BÁO GỬI CHO KHÁCH
            ->whereIn('id_loai_tb', [1, 2, 5, 7,])
            ->orderByDesc('created_at')
            ->get();

        // KHÔNG kiểm tra whereNull('id_nv')
        $binhLuan = DB::table('thong_bao')
            ->where('id_kh', $id_kh)
            ->where('id_loai_tb', [4, 6, 8, 9])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'don_hang' => $donHang,
            'binh_luan' => $binhLuan,
        ]);
    }

    // Lấy thông báo cho nhân viên
    public function getThongBaoNhanVien($id_nv)
    {
        // Thông báo đơn hàng
        $donHang = DB::table('thong_bao')
            ->where('id_nv', $id_nv)
            ->whereIn('id_loai_tb', [1, 5, 7]) // 1: Đơn hàng mới, 5: Khách hủy đơn, 7: Khách trả hàng
            ->orderByDesc('created_at')
            ->get();

        $binhLuan = DB::table('thong_bao')
            ->where('id_nv', $id_nv)
            ->whereIn('id_loai_tb', [3]) // 3 = Khách hàng đánh giá
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'don_hang' => $donHang,
            'binh_luan' => $binhLuan,
        ]);
    }
    public function danhDauDaDoc(Request $request)
    {
        $request->validate([
            'id_tb' => 'required|integer|exists:thong_bao,id_tb'
        ]);
        DB::table('thong_bao')->where('id_tb', $request->id_tb)->update(['da_doc' => 1]);
        return response()->json(['success' => true, 'message' => 'Đã đánh dấu đã đọc']);
    }
}
