public function getFilteredRooms($filters) {
    $query = PhongTro::query();

    if ($filters['status'] === 'con_phong') {
        $query->where('trang_thai', 'con_phong');
    } elseif ($filters['status'] === 'da_dat_coc') {
        $query->where('trang_thai', 'da_dat_coc');
    } elseif ($filters['status'] === 'da_thue') {
        $query->where('trang_thai', 'da_thue');
    }

    // Lấy danh sách phòng sau lọc
    $rooms = $query->get();

    // Tổng số phòng (từ bảng phongtro)
    $totalRooms = PhongTro::count(); // Đảm bảo lấy từ bảng phongtro

    // Số phòng hiển thị
    $displayedCount = $rooms->count();

    // Trả về dữ liệu với thông tin hiển thị
    return [
        'rooms' => $rooms,
        'displayed_count' => $displayedCount,
        'total_count' => $totalRooms
    ];
}