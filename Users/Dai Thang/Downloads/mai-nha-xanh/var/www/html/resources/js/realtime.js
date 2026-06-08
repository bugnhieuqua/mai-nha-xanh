
function updateRoomStats() {
    fetch('/api/room-stats')
        .then(response => response.json())
        .then(data => {
            document.getElementById('available-rooms').textContent = data.available;
            document.getElementById('booked-rooms').textContent = data.booked;
            document.getElementById('rented-rooms').textContent = data.rented;
            document.getElementById('total-rooms').textContent = data.total;

            // Cập nhật phần hiển thị trên giao diện
            const displayed = document.querySelector('.filter-info');
            if (displayed) {
                displayed.textContent = `Hiển thị ${data.displayed}/${data.total} phòng`;
            }
        })
        .catch(err => console.error("Lỗi cập nhật thống kê:", err));
}

// Gọi hàm mỗi 5 giây để cập nhật dữ liệu
setInterval(updateRoomStats, 5000);

// Khởi chạy ngay khi tải trang
updateRoomStats();