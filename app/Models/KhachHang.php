<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class KhachHang extends Authenticatable
{
    use HasFactory;

    protected $table = 'khach_hang';
    protected $primaryKey = 'id_kh';
    public $timestamps = true;

    protected $fillable = [
        'ten',
        'email',
        'sodienthoai',
        'password',
        'diachi',
        'diem',
        'id_quyen',
        'ngaysinh',
        'gioitinh'
    ];

    protected $hidden = [
        'password',
    ];
}
