<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;



class ThongKeController extends Controller
{
    public function thongKe(Request $request)
    {
        // Nhận kiểu thống kê và các tham số lọc
        $kieu = $request->input('kieu', 'ngay'); // ngay, thang, nam
        $ngay = $request->input('ngay');
        $thang = $request->input('thang');
        $nam = $request->input('nam');

        $query = DB::table('hoadon')->where('trangthai', '!=', 0); // ẩn/hủy thì loại

        // Lọc theo kiểu thống kê
        if ($kieu == 'ngay' && $ngay) {
            $query->whereDate('ngaylap', $ngay);
        } elseif ($kieu == 'thang' && $thang && $nam) {
            $query->whereMonth('ngaylap', $thang)
                ->whereYear('ngaylap', $nam);
        } elseif ($kieu == 'nam' && $nam) {
            $query->whereYear('ngaylap', $nam);
        }

        // Tổng doanh thu (chỉ tính đơn đã giao thành công, ví dụ id_ttdh = 4)
        $doanhthu = (clone $query)->where('id_ttdh', 4)->sum('tonggia');

        // Đếm số lượng đơn theo trạng thái
        $trangthais = [
            'cho_xu_ly' => 1,
            'dang_xu_ly' => 2,
            'dang_giao' => 3,
            'da_giao' => 4,
            'da_huy' => 5,
            'tra_hang' => 6,
        ];

        $counts = [];
        foreach ($trangthais as $key => $id_ttdh) {
            $counts[$key] = (clone $query)->where('id_ttdh', $id_ttdh)->count();
        }

        return response()->json([
            'doanhthu' => $doanhthu,
            'counts' => $counts,
        ]);
    }
}
