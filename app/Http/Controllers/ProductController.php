<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function addProduct(Request $request)
    {
        $request->validate([
            'tensp' => 'required|string|max:255',
            'gioitinh' => 'required|in:Nam,Nữ,Unisex',
            'chatlieu' => 'required|string|max:255',
            'tenpk' => 'nullable|string|max:255',
            'id_loai' => 'required|integer',
            'mota' => 'nullable|string',
            'sizes' => 'required|array|min:1',
            'sizes.*.size' => 'required|string',
            'sizes.*.quantity' => 'required|integer|min:0',
            'sizes.*.price' => 'required|integer|min:0',
            'donvi' => 'required|string|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'is_freesize' => 'required|boolean',
        ]);

        // Tính tổng số lượng
        $totalQuantity = array_sum(array_map(function ($sz) {
            return intval($sz['quantity']);
        }, $request->sizes));

        // Lấy giá nhỏ nhất trong các size
        $minPrice = min(array_map(function ($sz) {
            return intval($sz['price']);
        }, $request->sizes));

        // Lưu sản phẩm
        $id_sp = DB::table('sanpham')->insertGetId([
            'tensp' => $request->tensp,
            'gioitinh' => $request->gioitinh,
            'chatlieu' => $request->chatlieu,
            'tenpk' => $request->tenpk,
            'id_loai' => $request->id_loai,
            'mota' => $request->mota,
            'gia' => $minPrice,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Xử lý upload ảnh
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $filename = Str::random(10) . '_' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads'), $filename);
                $imagePaths[] = $filename;
            }
            foreach ($imagePaths as $img) {
                DB::table('hinhanh')->insert([
                    'duong_dan' => $img,
                    'id_sp' => $id_sp,
                ]);
            }
        }

        // Lưu size và chi tiết sản phẩm
        foreach ($request->sizes as $sz) {
            $kichthuoc = floatval($sz['size']);
            $quantity = intval($sz['quantity']);
            $price = intval($sz['price']);
            $size = DB::table('size')->where([
                ['kichthuoc', '=', $kichthuoc],
                ['donvi', '=', $request->donvi]
            ])->first();

            if (!$size) {
                $id_size = DB::table('size')->insertGetId([
                    'kichthuoc' => $kichthuoc,
                    'donvi' => $request->donvi,
                ]);
            } else {
                $id_size = $size->id_size;
            }

            DB::table('chitiet_sp')->insert([
                'id_sp' => $id_sp,
                'id_size' => $id_size,
                'gia' => $price,
                'soluong_kho' => $quantity,
                'soluong_daban' => 0,
                'trangthai' => 1,
            ]);
        }

        return response()->json([
            'message' => 'Thêm sản phẩm thành công',
            'id_sp' => $id_sp,
            'images' => $imagePaths,
        ]);
    }
    /// API danh sách sản phẩm (trang chủ)

    public function getProducts()
    {
        $products = DB::table('sanpham')
            ->leftJoin('hinhanh', 'sanpham.id_sp', '=', 'hinhanh.id_sp')
            ->leftJoin('loai', 'sanpham.id_loai', '=', 'loai.id_loai')
            // Subquery tổng số lượng kho
            ->leftJoin(
                DB::raw('(SELECT id_sp, SUM(soluong_kho) as tong_kho, SUM(soluong_daban) as tong_daban FROM chitiet_sp GROUP BY id_sp) as cts'),
                'sanpham.id_sp',
                '=',
                'cts.id_sp'
            )
            ->select(
                'sanpham.id_sp',
                'sanpham.tensp',
                'sanpham.gioitinh',
                'sanpham.chatlieu',
                'sanpham.tenpk',
                'sanpham.id_loai',
                'loai.tenloai',
                'sanpham.mota',
                'sanpham.gia',
                'sanpham.trangthai',
                'sanpham.created_at',
                'sanpham.updated_at',
                DB::raw('MIN(hinhanh.duong_dan) as image'),
                DB::raw('COALESCE(cts.tong_daban,0) as tong_daban'),
                DB::raw('COALESCE(cts.tong_kho,0) as tong_kho')
            )
            ->groupBy(
                'sanpham.id_sp',
                'sanpham.tensp',
                'sanpham.gioitinh',
                'sanpham.chatlieu',
                'sanpham.tenpk',
                'sanpham.id_loai',
                'loai.tenloai',
                'sanpham.mota',
                'sanpham.gia',
                'sanpham.trangthai',
                'sanpham.created_at',
                'sanpham.updated_at',
                'cts.tong_daban',
                'cts.tong_kho'
            )
            // Sản phẩm còn hàng (tong_kho > 0) lên trước, hết hàng xuống cuối
            ->orderByDesc(DB::raw('COALESCE(cts.tong_kho,0) > 0'))
            // Trong mỗi nhóm, sắp xếp theo lượt bán giảm dần
            ->orderByDesc('tong_daban')
            ->orderByDesc('sanpham.created_at')
            ->get();

        foreach ($products as $product) {
            $product->image_url = $product->image
                ? url('uploads/' . $product->image)
                : null;
            $product->details = DB::table('chitiet_sp')
                ->join('size', 'chitiet_sp.id_size', '=', 'size.id_size')
                ->select(
                    'chitiet_sp.id_ctsp',
                    'chitiet_sp.gia',
                    'chitiet_sp.soluong_kho',
                    'chitiet_sp.soluong_daban',
                    'chitiet_sp.trangthai',
                    'size.kichthuoc',
                    'size.donvi'
                )
                ->where('chitiet_sp.id_sp', $product->id_sp)
                ->get();
        }

        return response()->json(['products' => $products]);
    }
    ///API chi tiết sản phẩm

    public function getProductDetail($id)
    {
        $product = DB::table('sanpham')
            ->leftJoin('loai', 'sanpham.id_loai', '=', 'loai.id_loai')
            ->where('sanpham.id_sp', $id)
            ->select(
                'sanpham.id_sp',
                'sanpham.tensp',
                'sanpham.gioitinh',
                'sanpham.chatlieu',
                'sanpham.tenpk',
                'sanpham.id_loai',
                'trangthai',
                'loai.tenloai',
                'sanpham.mota',

                'sanpham.created_at',
                'sanpham.updated_at'
            )
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        // Lấy tất cả ảnh
        $images = DB::table('hinhanh')
            ->where('id_sp', $id)
            ->pluck('duong_dan')
            ->map(function ($img) {
                return url('uploads/' . $img);
            });

        // Lấy tất cả size/giá/số lượng
        $details = DB::table('chitiet_sp')
            ->join('size', 'chitiet_sp.id_size', '=', 'size.id_size')
            ->select(
                'chitiet_sp.id_ctsp',
                'chitiet_sp.gia',
                'chitiet_sp.soluong_kho',
                'chitiet_sp.soluong_daban',
                'chitiet_sp.trangthai',
                'size.kichthuoc',
                'size.donvi'
            )
            ->where('chitiet_sp.id_sp', $id)
            ->get();

        $product->images = $images;
        $product->details = $details;

        return response()->json(['product' => $product]);
    }
    // API lấy thông tin dat_rieng
    public function getDatRieng($id)
    {
        $data = DB::table('dat_rieng')->where('id_dr', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }
        return response()->json(['data' => $data]);
    }
    // API cập nhật thông tin dat_rieng
    public function updateDatRieng(Request $request, $id)
    {
        $request->validate([
            'sodienthoai' => 'nullable|string|max:12',
            'zalo' => 'nullable|string|max:12',
        ]);

        $affected = DB::table('dat_rieng')
            ->where('id_dr', $id)
            ->update([
                'sodienthoai' => $request->sodienthoai,
                'zalo' => $request->zalo,
            ]);

        if ($affected) {
            return response()->json(['message' => 'Cập nhật thành công']);
        } else {
            return response()->json(['message' => 'Không tìm thấy hoặc không có thay đổi'], 404);
        }
    }


    // API cập nhật sản phẩm

    public function updateProduct(Request $request, $id)
    {
        $request->validate([
            'tensp' => 'sometimes|required|string|max:255',
            'gioitinh' => 'sometimes|required|in:Nam,Nữ,Unisex',
            'chatlieu' => 'sometimes|required|string|max:255',
            'tenpk' => 'nullable|string|max:255',
            'id_loai' => 'sometimes|required|integer',
            'mota' => 'nullable|string',
            'trangthai' => 'nullable|integer|in:0,1',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'delete_images' => 'array',
            'delete_images.*' => 'string',
            'sizes' => 'sometimes|array|min:1',
            'sizes.*.size' => 'required_with:sizes',
            'sizes.*.quantity' => 'required_with:sizes|integer|min:0',
            'sizes.*.price' => 'required_with:sizes|integer|min:0',
            'sizes.*.donvi' => 'required_with:sizes|string|max:10',
            'is_freesize' => 'sometimes|boolean',
            'donvi' => 'sometimes|string|max:10',
        ]);

        try {
            DB::beginTransaction();

            // Kiểm tra sản phẩm
            $product = DB::table('sanpham')->where('id_sp', $id)->first();
            if (!$product) {
                return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
            }

            // Cập nhật thông tin cơ bản
            $updateData = [];
            foreach (['tensp', 'gioitinh', 'chatlieu', 'tenpk', 'id_loai', 'mota', 'trangthai'] as $field) {
                if ($request->has($field))
                    $updateData[$field] = $request->$field;
            }
            $updateData['updated_at'] = now();
            if (count($updateData) > 1) {
                DB::table('sanpham')->where('id_sp', $id)->update($updateData);
            }

            // Xử lý xóa ảnh cũ
            $deletedImages = [];
            if ($request->has('delete_images') && is_array($request->delete_images)) {
                foreach ($request->delete_images as $img) {
                    $filename = basename($img);
                    $deleted = DB::table('hinhanh')->where('id_sp', $id)->where('duong_dan', $filename)->delete();
                    if ($deleted) {
                        $imgPath = public_path('uploads/' . $filename);
                        if (file_exists($imgPath))
                            @unlink($imgPath);
                        $deletedImages[] = $filename;
                    }
                }
            }

            // Xử lý upload ảnh mới
            $newImagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $filename = Str::random(10) . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('uploads'), $filename);
                    DB::table('hinhanh')->insert([
                        'duong_dan' => $filename,
                        'id_sp' => $id,
                    ]);
                    $newImagePaths[] = $filename;
                }
            }

            // Xử lý cập nhật size và chi tiết sản phẩm nếu có truyền lên
            if ($request->has('sizes') && is_array($request->sizes) && count($request->sizes) > 0) {
                // Lấy danh sách id_ctsp cũ
                $ctspList = DB::table('chitiet_sp')->where('id_sp', $id)->pluck('id_ctsp');
                $ctspHasOrder = DB::table('chitiet_hd')->whereIn('id_ctsp', $ctspList)->pluck('id_ctsp')->toArray();

                // Chỉ xóa những size không bị ràng buộc hóa đơn
                foreach ($ctspList as $id_ctsp) {
                    if (!in_array($id_ctsp, $ctspHasOrder)) {
                        DB::table('chitiet_sp')->where('id_ctsp', $id_ctsp)->delete();
                    } else {
                        // Nếu đã có hóa đơn, chỉ cập nhật lại số lượng và giá nếu size trùng
                        // hoặc giữ nguyên, không xóa
                    }
                }

                $totalQuantity = 0;
                $minPrice = null;

                foreach ($request->sizes as $sz) {
                    $kichthuoc = floatval($sz['size']);
                    $quantity = intval($sz['quantity']);
                    $price = intval($sz['price']);
                    $donvi = $sz['donvi'] ?? $request->donvi ?? 'freesize';

                    // Tìm hoặc tạo size
                    $size = DB::table('size')->where([
                        ['kichthuoc', '=', $kichthuoc],
                        ['donvi', '=', $donvi]
                    ])->first();
                    if (!$size) {
                        $id_size = DB::table('size')->insertGetId([
                            'kichthuoc' => $kichthuoc,
                            'donvi' => $donvi,
                        ]);
                    } else {
                        $id_size = $size->id_size;
                    }

                    // Nếu đã có chitiet_sp với size này thì update, chưa có thì insert
                    $ctsp = DB::table('chitiet_sp')->where('id_sp', $id)->where('id_size', $id_size)->first();
                    if ($ctsp) {
                        DB::table('chitiet_sp')->where('id_ctsp', $ctsp->id_ctsp)->update([
                            'gia' => $price,
                            'soluong_kho' => $quantity,
                            'trangthai' => 1,
                        ]);
                    } else {
                        DB::table('chitiet_sp')->insert([
                            'id_sp' => $id,
                            'id_size' => $id_size,
                            'gia' => $price,
                            'soluong_kho' => $quantity,
                            'soluong_daban' => 0,
                            'trangthai' => 1,
                        ]);
                    }

                    $totalQuantity += $quantity;
                    if ($minPrice === null || $price < $minPrice)
                        $minPrice = $price;
                }
                // Tính lại tổng số lượng kho
                $totalQuantity = DB::table('chitiet_sp')
                    ->where('id_sp', $id)
                    ->sum('soluong_kho');

                // Cập nhật lại tổng số lượng kho cho sản phẩm
                DB::table('sanpham')
                    ->where('id_sp', $id);


                // Cập nhật lại tổng số lượng và giá nhỏ nhất cho sản phẩm
                DB::table('sanpham')->where('id_sp', $id)->update([

                    'gia' => $minPrice ?? $product->gia,
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Cập nhật sản phẩm thành công',
                'deleted_images' => $deletedImages,
                'new_images' => $newImagePaths,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($newImagePaths ?? [] as $img) {
                $imgPath = public_path('uploads/' . $img);
                if (file_exists($imgPath))
                    @unlink($imgPath);
            }
            return response()->json([
                'message' => 'Có lỗi xảy ra khi cập nhật sản phẩm: ' . $e->getMessage()
            ], 500);
        }
    }

    // API ẩn sản phẩm
    public function hideProduct($id)
    {
        $affected = DB::table('sanpham')
            ->where('id_sp', $id)
            ->update(['trangthai' => 0, 'updated_at' => now()]);

        // Ẩn luôn tất cả chi tiết sản phẩm
        DB::table('chitiet_sp')
            ->where('id_sp', $id)
            ->update(['trangthai' => 0]);

        if ($affected) {
            return response()->json(['message' => 'Ẩn sản phẩm thành công']);
        } else {
            return response()->json(['message' => 'Không tìm thấy sản phẩm hoặc đã ẩn'], 404);
        }
    }
    //// API hiện lại sản phẩm đã ẩn
    public function showProduct($id)
    {
        $affected = DB::table('sanpham')
            ->where('id_sp', $id)
            ->update(['trangthai' => 1, 'updated_at' => now()]);

        // Hiện lại tất cả chi tiết sản phẩm
        DB::table('chitiet_sp')
            ->where('id_sp', $id)
            ->update(['trangthai' => 1]);

        if ($affected) {
            return response()->json(['message' => 'Hiện sản phẩm thành công']);
        } else {
            return response()->json(['message' => 'Không tìm thấy sản phẩm hoặc đã hiện'], 404);
        }
    }
    public function updateStock(Request $request, $id)
    {
        $product = DB::table('sanpham')->where('id_sp', $id)->first();
        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        // Tính tổng số lượng kho thực tế từ chitiet_sp
        $tong_so_luong = DB::table('chitiet_sp')
            ->where('id_sp', $id)
            ->sum('soluong_kho');
        $trangthai = $tong_so_luong > 0 ? 1 : 0;

        DB::table('sanpham')
            ->where('id_sp', $id)
            ->update([
                'trangthai' => $trangthai,
                'updated_at' => now(),
            ]);

        // Cập nhật trạng thái cho tất cả chi tiết sản phẩm
        DB::table('chitiet_sp')
            ->where('id_sp', $id)
            ->update(['trangthai' => $trangthai]);

        return response()->json([
            'message' => 'Cập nhật trạng thái tồn kho thành công',
            'trangthai' => $trangthai
        ]);
    }
}
