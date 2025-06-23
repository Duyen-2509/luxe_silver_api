<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    // API lấy danh sách voucher
    public function getVoucher()
    {
        $vouchers = DB::table('voucher')
            ->leftJoin('loai_voucher', 'voucher.id_loai_voucher', '=', 'loai_voucher.id_loai_voucher')
            ->select(
                'voucher.*',
                'loai_voucher.ten as ten_loai_voucher'
            )
            ->orderByDesc('voucher.created_at')
            ->get();

        return response()->json(['voucher' => $vouchers]);
    }

    // API thêm voucher
    public function addVoucher(Request $request)
    {
        $request->validate([
            'id_loai_voucher' => 'required|integer|exists:loai_voucher,id_loai_voucher',
            'ten' => 'required|string|max:255',
            'giatri_min' => 'required|integer|min:0',
            'sotiengiam' => 'required|numeric|min:0',
            'soluong' => 'required|integer|min:1',
            'ngaybatdau' => 'required|date',
            'ngayketthuc' => 'required|date|after_or_equal:ngaybatdau',
            'trangthai' => 'nullable|boolean',
        ]);

        $id = DB::table('voucher')->insertGetId([
            'id_loai_voucher' => $request->id_loai_voucher,
            'ten' => $request->ten,
            'giatri_min' => $request->giatri_min,
            'sotiengiam' => $request->sotiengiam,
            'soluong' => $request->soluong,
            'ngaybatdau' => $request->ngaybatdau,
            'ngayketthuc' => $request->ngayketthuc,
            'trangthai' => $request->trangthai ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Thêm voucher thành công', 'id' => $id]);
    }

    // API chỉnh sửa voucher
    public function updateVoucher(Request $request, $id)
    {
        $request->validate([
            'id_loai_voucher' => 'required|integer|exists:loai_voucher,id_loai_voucher',
            'ten' => 'required|string|max:255',
            'giatri_min' => 'required|integer|min:0',
            'sotiengiam' => 'required|numeric|min:0',
            'soluong' => 'required|integer|min:1',
            'ngaybatdau' => 'required|date',
            'ngayketthuc' => 'required|date|after_or_equal:ngaybatdau',
            'trangthai' => 'nullable|boolean',
        ]);

        $affected = DB::table('voucher')->where('id_voucher', $id)->update([
            'id_loai_voucher' => $request->id_loai_voucher,
            'ten' => $request->ten,
            'giatri_min' => $request->giatri_min,
            'sotiengiam' => $request->sotiengiam,
            'soluong' => $request->soluong,
            'ngaybatdau' => $request->ngaybatdau,
            'ngayketthuc' => $request->ngayketthuc,
            'trangthai' => $request->trangthai ?? 1,
            'updated_at' => now(),
        ]);

        if ($affected) {
            return response()->json(['message' => 'Cập nhật voucher thành công']);
        } else {
            return response()->json(['message' => 'Không tìm thấy voucher hoặc không có thay đổi'], 404);
        }
    }

    // API lấy loại voucher
    public function getLoaiVoucher()
    {
        $loaiVouchers = DB::table('loai_voucher')->get();
        return response()->json(['loai_voucher' => $loaiVouchers]);
    }
}
