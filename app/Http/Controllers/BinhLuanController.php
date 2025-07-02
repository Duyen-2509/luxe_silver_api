<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BinhLuanController extends Controller
{
    // Khách hàng đánh giá sản phẩm (chỉ khi đơn đã nhận)

    public function danhGia(Request $request)
    {
        $request->validate([
            'mahd' => 'required|exists:hoadon,mahd',
            'id_kh' => 'required|exists:khach_hang,id_kh',
            'id_sp' => 'required|exists:sanpham,id_sp',
            'sosao' => 'required|integer|min:1|max:5',
            'noidung' => 'required|string|max:1000',
        ]);

        // Kiểm tra đơn đã nhận
        $hoadon = DB::table('hoadon')
            ->where('mahd', $request->mahd)
            ->where('id_kh', $request->id_kh)
            ->where('id_ttdh', 4)
            ->first();
        if (!$hoadon) {
            return response()->json(['message' => 'Chỉ đánh giá khi đơn đã nhận'], 403);
        }

        // Kiểm tra đã đánh giá sản phẩm này trong đơn này chưa
        $binhluan = DB::table('binhluan')->where([
            'mahd' => $request->mahd,
            'id_kh' => $request->id_kh,

            'trangthai' => 1
        ])->first();

        if ($binhluan) {
            // Kiểm tra trùng ở chitiet_binhluan
            $ct_binhluan = DB::table('chitiet_binhluan')
                ->where('id_bl', $binhluan->id_bl)
                ->where('id_sp', $request->id_sp)
                ->where('trangthai', 1)
                ->first();
            if ($ct_binhluan) {
                return response()->json(['message' => 'Bạn đã đánh giá sản phẩm này. Hãy dùng API sửa nếu muốn chỉnh sửa.'], 409);
            }
            $id_bl = $binhluan->id_bl;
        } else {
            // Tạo bình luận gốc (không cần id_sp)
            $id_bl = DB::table('binhluan')->insertGetId([
                'mahd' => $request->mahd,
                'id_kh' => $request->id_kh,
                'solan_sua' => 0,
                'trangthai' => 1,
                'created_at' => now(),
            ]);
        }



        // Tạo chi tiết bình luận cho sản phẩm này
        DB::table('chitiet_binhluan')->insert([
            'id_bl' => $id_bl,
            'id_sp' => $request->id_sp,
            'noidung' => $request->noidung,
            'sosao' => $request->sosao,
            'trangthai' => 1,
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Đánh giá thành công']);
    }
    // Khách hàng sửa đánh giá (tối đa 2 lần)
    public function suaDanhGia(Request $request, $id_bl)
    {
        $request->validate([
            'id_kh' => 'required|exists:khach_hang,id_kh',
            'id_sp' => 'required|exists:sanpham,id_sp',
            'sosao' => 'required|integer|min:1|max:5',
            'noidung' => 'required|string|max:1000',
        ]);

        $binhluan = DB::table('binhluan')->where('id_bl', $id_bl)->where('id_kh', $request->id_kh)->where('trangthai', 1)->first();
        if (!$binhluan) {
            return response()->json(['message' => 'Không tìm thấy bình luận'], 404);
        }
        if ($binhluan->solan_sua >= 2) {
            return response()->json(['message' => 'Bạn chỉ được sửa tối đa 2 lần'], 403);
        }

        // Lấy bản ghi chi tiết bình luận mới nhất
        $ct_binhluan = DB::table('chitiet_binhluan')
            ->where('id_bl', $id_bl)
            ->where('id_sp', $request->id_sp)
            ->where('trangthai', 1)
            ->orderByDesc('created_at')
            ->first();

        if (!$ct_binhluan) {
            return response()->json(['message' => 'Không tìm thấy chi tiết bình luận'], 404);
        }

        // Ghi đè lại nội dung và số sao
        DB::table('chitiet_binhluan')->where('id_ctbl', $ct_binhluan->id_ctbl)->update([
            'noidung' => $request->noidung,
            'sosao' => $request->sosao,
            'updated_at' => now(),
        ]);

        // Tăng số lần sửa
        DB::table('binhluan')->where('id_bl', $id_bl)->update([
            'solan_sua' => $binhluan->solan_sua + 1,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Sửa đánh giá thành công']);
    }

    // Khách hàng xóa đánh giá (chuyển trạng thái = 0)
    public function xoaDanhGia(Request $request, $id_bl)
    {
        $request->validate([
            'id_kh' => 'required|exists:khach_hang,id_kh',
        ]);
        $binhluan = DB::table('binhluan')->where('id_bl', $id_bl)->where('id_kh', $request->id_kh)->first();
        if (!$binhluan) {
            return response()->json(['message' => 'Không tìm thấy bình luận'], 404);
        }
        DB::table('binhluan')->where('id_bl', $id_bl)->update([
            'trangthai' => 0,
            'updated_at' => now(),
        ]);
        // Cập nhật trạng thái các chi tiết
        DB::table('chitiet_binhluan')->where('id_bl', $id_bl)->update([
            'trangthai' => 0,
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Đã xóa đánh giá']);
    }


    // Thống kê đánh giá sản phẩm
    public function thongKeDanhGia($id_sp)
    {
        $result = DB::table('chitiet_binhluan')
            ->where('id_sp', $id_sp)
            ->where('trangthai', 1)
            ->selectRaw('COUNT(*) as so_luot, AVG(sosao) as trung_binh')
            ->first();

        $so_luot = $result->so_luot ?? 0;
        $trung_binh = $result->trung_binh !== null ? round($result->trung_binh, 2) : 5;

        return response()->json([
            'so_luot' => $so_luot,
            'trung_binh' => $trung_binh
        ]);
    }
    public function getBinhLuanSanPham($id_sp)
    {
        $binhluans = DB::table('binhluan')
            ->join('chitiet_binhluan', 'binhluan.id_bl', '=', 'chitiet_binhluan.id_bl')
            ->join('khach_hang', 'binhluan.id_kh', '=', 'khach_hang.id_kh')
            ->where('chitiet_binhluan.id_sp', $id_sp)
            ->where('binhluan.trangthai', 1)
            ->where('chitiet_binhluan.trangthai', 1)
            ->select(
                'binhluan.id_bl',
                'binhluan.id_kh',
                'khach_hang.ten as ten_khach_hang',
                'binhluan.mahd',
                'binhluan.solan_sua',
                'binhluan.created_at as bl_created_at',
                'chitiet_binhluan.id_ctbl',
                'chitiet_binhluan.noidung',
                'chitiet_binhluan.sosao',
                'chitiet_binhluan.id_nv',
                'chitiet_binhluan.traloi_kh',
                'chitiet_binhluan.ten_nhan_vien',
                'chitiet_binhluan.created_at as ct_created_at'
            )
            ->orderBy('chitiet_binhluan.created_at', 'desc')
            ->get();

        return response()->json(['binhluan' => $binhluans]);
    }
    // Nhân viên trả lời bình luận (tạo bản ghi mới trong chitiet_binhluan)

    public function traLoiBinhLuan(Request $request, $id_bl)
    {
        $request->validate([
            'id_nv' => 'required|exists:nhan_vien,id_nv',
            'traloi_kh' => 'required|string|max:1000',
            'id_sp' => 'required|exists:sanpham,id_sp',
        ]);
        // Lấy tên nhân viên
        $ten_nhan_vien = DB::table('nhan_vien')->where('id_nv', $request->id_nv)->value('ten');

        // Tìm chi tiết bình luận của khách hàng còn hiệu lực
        $ct_binhluan = DB::table('chitiet_binhluan')
            ->where('id_bl', $id_bl)
            ->where('id_sp', $request->id_sp)
            ->where('trangthai', 1)
            ->first();

        if (!$ct_binhluan) {
            return response()->json(['message' => 'Không tìm thấy chi tiết bình luận của khách hàng'], 404);
        }

        // Cập nhật trả lời vào dòng này, đồng thời cập nhật tên nhân viên
        DB::table('chitiet_binhluan')->where('id_ctbl', $ct_binhluan->id_ctbl)->update([
            'id_nv' => $request->id_nv,
            'traloi_kh' => $request->traloi_kh,
            'ten_nhan_vien' => $ten_nhan_vien,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Đã trả lời bình luận',
            'ten_nhan_vien' => $ten_nhan_vien,
            'id_nv' => $request->id_nv,
        ]);
    }


    // Nhân viên xóa trả lời bình luận (cập nhật traloi_kh về null)
    public function xoaTraLoiBinhLuan(Request $request, $id_ctbl)
    {
        $request->validate([
            'id_nv' => 'required|exists:nhan_vien,id_nv',
        ]);
        // Bỏ điều kiện where('id_nv', $request->id_nv)
        $ctbl = DB::table('chitiet_binhluan')
            ->where('id_ctbl', $id_ctbl)
            ->first();
        if (!$ctbl) {
            return response()->json(['message' => 'Không tìm thấy trả lời này'], 404);
        }
        DB::table('chitiet_binhluan')->where('id_ctbl', $id_ctbl)->update([
            'traloi_kh' => null,
            'id_nv' => null,
            'ten_nhan_vien' => null,
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Đã xóa trả lời bình luận']);
    }
}
