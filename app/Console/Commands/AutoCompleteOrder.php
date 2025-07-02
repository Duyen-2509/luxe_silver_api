<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoCompleteOrder extends Command
{
    protected $signature = 'order:auto-complete';
    protected $description = 'Tự động chuyển trạng thái đơn đã giao sang thành công sau 7 ngày';

    public function handle()
    {
        $orders = DB::table('hoadon')
            ->where('id_ttdh', 3) // Đang giao
            ->where('trangthai', 2) // Đã giao cho khách
            ->where('updated_at', '<=', now()->subDays(2)) // subMinutes(1)|subDays(7)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Không có đơn nào cần chuyển trạng thái.');
            return;
        }

        foreach ($orders as $order) {
            // Chuyển hóa đơn sang đã nhận
            DB::table('hoadon')->where('mahd', $order->mahd)->update([
                'id_ttdh' => 4, // Đã nhận
                'trangthai' => 1,
                'updated_at' => now(),
            ]);
            // Đồng bộ chi tiết hóa đơn
            DB::table('chitiet_hd')->where('mahd', $order->mahd)->update([
                'trangthai' => 1,
                'updated_at' => now(),
            ]);
            $this->info("Đã chuyển đơn {$order->mahd} sang trạng thái ĐÃ NHẬN.");
        }
    }
}
