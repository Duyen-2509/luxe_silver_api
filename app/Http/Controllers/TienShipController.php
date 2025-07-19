<?php


namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TienShipController extends Controller
{
    // Lấy danh sách giá ship
    public function getTienShip()
    {
        $data = DB::table('tien_ship')->get();
        return response()->json(['tien_ship' => $data]);
    }

    // Cập nhật giá ship
    public function updateTienShip(Request $request, $id)
    {
        $request->validate([
            'gia' => 'required|numeric|min:0'
        ]);
        $affected = DB::table('tien_ship')
            ->where('id_tien_ship', $id)
            ->update(['gia' => $request->gia, 'updated_at' => now()]);
        if ($affected) {
            return response()->json(['message' => 'Cập nhật giá ship thành công']);
        } else {
            return response()->json(['message' => 'Không tìm thấy hoặc không có thay đổi'], 404);
        }
    }
}
