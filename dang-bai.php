<?php 
require_once 'config/bootstrap.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Lấy thông tin chủ nhà từ bài đăng gần nhất hoặc thông tin cá nhân
$ten_chunha = '';
$sdt_chunha = '';
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("SELECT ten_chunha, sdt_chunha FROM dangbai_chothuetro WHERE nguoidang = :username ORDER BY id DESC LIMIT 1");
    $stmt->execute([':username' => $_SESSION['username']]);
    $lastPost = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($lastPost) {
        $ten_chunha = $lastPost['ten_chunha'] ?? '';
        $sdt_chunha = $lastPost['sdt_chunha'] ?? '';
    } else {
        $ten_chunha = $_SESSION['hoten'] ?? '';
    }
} catch (Exception $e) {
    // Bỏ qua lỗi
}

$page_title = "Đăng bài cho thuê trọ";
include 'includes/header.php'; 
?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1 class="typing-effect">Đăng bài cho thuê trọ</h1>
        <p class="animate-fade-up" style="animation-delay: 0.2s;">Đăng tin cho thuê phòng trọ của bạn để tiếp cận nhiều người thuê hơn</p>
    </div>
</section>

<!-- Post Form Section -->
<section class="post-room-section">
    <div class="container">
        <div class="post-form-wrapper">
            <div class="post-form-header">
                <h2><i class="fas fa-plus-circle"></i> Thông tin phòng trọ</h2>
                <p>Vui lòng điền đầy đủ thông tin để bài đăng được duyệt nhanh hơn</p>
            </div>

            <form id="postRoomForm" class="post-room-form" enctype="multipart/form-data">
                <!-- Tiêu đề -->
                <div class="form-group">
                    <label for="tieude"><i class="fas fa-heading"></i> Tiêu đề bài đăng <span class="required">*</span></label>
                    <input type="text" id="tieude" name="tieude" class="form-control" placeholder="VD: Phòng trọ giá rẻ gần Đại học Kinh tế Nghệ An" required>
                </div>

                <!-- Giá và Diện tích -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="gia"><i class="fas fa-money-bill-wave"></i> Giá thuê/tháng (VNĐ) <span class="required">*</span></label>
                        <input type="number" id="gia" name="gia" class="form-control" placeholder="VD: 2000000" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="dientich"><i class="fas fa-ruler-combined"></i> Diện tích (m²) <span class="required">*</span></label>
                        <input type="number" id="dientich" name="dientich" class="form-control" placeholder="VD: 25" min="0" step="0.1" required>
                    </div>
                </div>

                <!-- Địa chỉ -->
                <div class="form-group">
                    <label for="diachi"><i class="fas fa-map-marker-alt"></i> Địa chỉ <span class="required">*</span></label>
                    <input type="text" id="diachi" name="diachi" class="form-control" placeholder="VD: 123 Đường Lê Duẩn, TP. Vinh, Nghệ An" required>
                </div>

                <!-- Mô tả -->
                <div class="form-group">
                    <label for="mota"><i class="fas fa-align-left"></i> Mô tả chi tiết</label>
                    <textarea id="mota" name="mota" class="form-control" rows="4" placeholder="Mô tả chi tiết về phòng trọ: vị trí, tiện ích xung quanh, nội thất..."></textarea>
                </div>

                <!-- Tiện nghi -->
                <div class="form-group">
                    <label><i class="fas fa-concierge-bell"></i> Tiện nghi</label>
                    <div class="amenities-grid">
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Wifi">
                            <span><i class="fas fa-wifi"></i> Wifi</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Máy lạnh">
                            <span><i class="fas fa-snowflake"></i> Máy lạnh</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Nóng lạnh">
                            <span><i class="fas fa-hot-tub"></i> Nóng lạnh</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Tủ lạnh">
                            <span><i class="fas fa-box"></i> Tủ lạnh</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Máy giặt">
                            <span><i class="fas fa-tshirt"></i> Máy giặt</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Giường">
                            <span><i class="fas fa-bed"></i> Giường</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Bàn ghế">
                            <span><i class="fas fa-chair"></i> Bàn ghế</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Tủ quần áo">
                            <span><i class="fas fa-door-closed"></i> Tủ quần áo</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Gác lửng">
                            <span><i class="fas fa-layer-group"></i> Gác lửng</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Chỗ để xe">
                            <span><i class="fas fa-motorcycle"></i> Chỗ để xe</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Camera an ninh">
                            <span><i class="fas fa-video"></i> Camera an ninh</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="tiennghi[]" value="Tự do giờ giấc">
                            <span><i class="fas fa-clock"></i> Tự do giờ giấc</span>
                        </label>
                    </div>
                    <div class="form-group" style="margin-top: 10px;">
                        <input type="text" name="tiennghi_khac" class="form-control" placeholder="Tiện nghi khác (cách nhau bởi dấu phẩy)">
                    </div>
                </div>

                <!-- Hình ảnh -->
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label for="hinhanh" style="margin: 0;"><i class="fas fa-camera"></i> Hình ảnh phòng trọ <span class="required">*</span></label>
                        <button type="button" id="ai-autofill-btn" class="btn btn-xs btn-outline" style="display: none; background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%); color: white; border: none; padding: 6px 14px; border-radius: 20px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 10px rgba(16,185,129,0.3);"><i class="fas fa-magic"></i> Điền nhanh bằng AI ✨</button>
                    </div>
                    <div class="image-upload-area" id="imageUploadArea">
                        <input type="file" id="hinhanh" name="hinhanh[]" accept="image/*" multiple required hidden>
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Nhấn để chọn 1-5 ảnh hoặc kéo thả vào đây</p>
                            <small>Bắt buộc ít nhất 1 ảnh, tối đa 5 ảnh</small>
                        </div>
                        <div class="image-preview" id="imagePreview" style="display:none;">
                            <div id="previewGrid" class="preview-grid"></div>
                            <button type="button" class="remove-image" id="removeImage">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Video -->
                <div class="form-group">
                    <label for="video"><i class="fas fa-video"></i> Video phòng trọ (không bắt buộc)</label>
                    <div class="image-upload-area" id="videoUploadArea">
                        <input type="file" id="video" name="video" accept="video/*" hidden>
                        <div class="upload-placeholder" id="videoPlaceholder">
                            <i class="fas fa-film"></i>
                            <p>Nhấn để chọn video hoặc kéo thả vào đây</p>
                            <small>Không bắt buộc, hỗ trợ nhiều định dạng video</small>
                        </div>
                        <div class="image-preview" id="videoPreview" style="display:none;">
                            <video id="previewVid" src="" controls style="max-width:100%; max-height:300px; border-radius:10px;"></video>
                            <button type="button" class="remove-image" id="removeVideo">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Thông tin chủ nhà -->
                <div class="form-section-title">
                    <h3><i class="fas fa-user-tie"></i> Thông tin chủ nhà</h3>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="ten_chunha"><i class="fas fa-user"></i> Tên chủ nhà <span class="required">*</span></label>
                        <input type="text" id="ten_chunha" name="ten_chunha" class="form-control" placeholder="VD: Nguyễn Văn A" value="<?php echo htmlspecialchars($ten_chunha); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="sdt_chunha"><i class="fas fa-phone"></i> Số điện thoại <span class="required">*</span></label>
                        <input type="tel" id="sdt_chunha" name="sdt_chunha" class="form-control" placeholder="VD: 0912345678" pattern="[0-9]{10,11}" value="<?php echo htmlspecialchars($sdt_chunha); ?>" required>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn btn-primary btn-block btn-submit-post">
                    <i class="fas fa-paper-plane"></i> Đăng bài
                </button>
            </form>
        </div>
    </div>
</section>

<style>
/* Post Room Form Styles */
.post-room-section {
    padding: 40px 0 60px;
    background: #f8fafc;
}

.post-form-wrapper {
    max-width: 800px;
    margin: 0 auto;
    background: var(--white);
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}


.post-form-header {
    text-align: center;
    margin-bottom: 30px;
}

.post-form-header h2 {
    font-size: 1.8rem;
    color: var(--dark-color);
    margin-bottom: 8px;
}


.post-form-header h2 i {
    color: #10b981;
}

.post-form-header p {
    color: var(--gray);
    font-size: 0.95rem;
}


.post-room-form .form-group {
    margin-bottom: 20px;
}

.post-room-form label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: var(--dark-color);
    font-size: 0.95rem;
}


.post-room-form label i {
    color: var(--primary-color);
    margin-right: 6px;
    width: 18px;
    text-align: center;
}


.post-room-form .required {
    color: var(--danger);
}

.post-room-form .form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 0.95rem;
    font-family: inherit;
    transition: border-color 0.3s, box-shadow 0.3s;
    background: #fafafa;
}

.post-room-form .form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
    background: #fff;
}


.post-room-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* Amenities Grid */
.amenities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    margin-top: 8px;
}

.amenity-checkbox {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.amenity-checkbox input[type="checkbox"] {
    display: none;
}

.amenity-checkbox span {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 0.9rem;
    transition: all 0.2s;
    width: 100%;
    color: #555;
}

.amenity-checkbox span i {
    color: var(--primary-color);
    font-size: 0.85rem;
}

.amenity-checkbox input:checked + span {
    border-color: var(--primary-color);
    background: rgba(118, 75, 162, 0.08);
    color: var(--primary-color);
    font-weight: 500;
}


/* Image Upload */
.image-upload-area {
    border: 2px dashed #d0d0d0;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.3s, background 0.3s;
    background: #fafafa;
}

.image-upload-area:hover {
    border-color: var(--primary-color);
    background: rgba(118, 75, 162, 0.03);
}


.upload-placeholder {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.upload-placeholder i {
    font-size: 3rem;
    color: var(--primary-color);
    margin-bottom: 10px;
    display: block;
}


.upload-placeholder p {
    font-size: 1rem;
    margin-bottom: 4px;
}

.upload-placeholder small {
    font-size: 0.8rem;
    color: #bbb;
}

.image-preview {
    position: relative;
    padding: 15px 15px 20px;
}

.image-preview img {
    max-width: 100%;
    max-height: 300px;
    border-radius: 10px;
    object-fit: contain;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
    margin-top: 6px;
}

.preview-thumb {
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid #e5e7eb;
    background: #f8fafc;
}

/* Submit Button */
.btn-submit-post {
    margin-top: 20px;
    padding: 14px 30px;
    font-size: 1.1rem;
    font-weight: 600;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
}

.btn-submit-post:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(118, 75, 162, 0.35);
}

/* Responsive */
@media (max-width: 768px) {
    .post-form-wrapper {
        padding: 25px 20px;
    }
    .post-room-form .form-row {
        grid-template-columns: 1fr;
    }
    .amenities-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
// Image Upload Preview
var uploadArea = document.getElementById('imageUploadArea');
var fileInput = document.getElementById('hinhanh');
var placeholder = document.getElementById('uploadPlaceholder');
var preview = document.getElementById('imagePreview');
var previewGrid = document.getElementById('previewGrid');
var removeBtn = document.getElementById('removeImage');
var selectedImageFiles = [];

function imageTypeAllowed(file) {
    return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type);
}

function syncImageInputState() {
    try {
        var dt = new DataTransfer();
        selectedImageFiles.forEach(function(file) {
            dt.items.add(file);
        });
        fileInput.files = dt.files;
    } catch (error) {
        // Một số trình duyệt chặn gán file thủ công, submit sẽ dùng selectedImageFiles làm nguồn chính.
    }
}

function getSelectedImageFiles() {
    return selectedImageFiles.slice();
}

function imageFingerprint(file) {
    return [file.name, file.size, file.lastModified].join('::');
}

function mergeImageFiles(fileList) {
    var merged = getSelectedImageFiles();
    var seen = new Set(merged.map(imageFingerprint));

    Array.from(fileList || []).forEach(function(file) {
        var key = imageFingerprint(file);
        if (!seen.has(key)) {
            merged.push(file);
            seen.add(key);
        }
    });

    return merged;
}

function renderImagePreviews() {
    previewGrid.innerHTML = '';
    var files = getSelectedImageFiles();
    var aiBtn = document.getElementById('ai-autofill-btn');
    if (!files.length) {
        preview.style.display = 'none';
        placeholder.style.display = 'block';
        fileInput.required = true;
        if (aiBtn) aiBtn.style.display = 'none';
        return;
    }

    files.forEach(function(file, idx) {
        var img = document.createElement('img');
        img.className = 'preview-thumb';
        img.alt = 'Ảnh preview ' + (idx + 1);
        img.src = URL.createObjectURL(file);
        img.onload = function() { URL.revokeObjectURL(img.src); };
        previewGrid.appendChild(img);
    });

    preview.style.display = 'block';
    placeholder.style.display = 'none';
    fileInput.required = false;
    if (aiBtn) aiBtn.style.display = 'block';
}

// Trợ lý AI Điền Form Đăng Bài
document.addEventListener("DOMContentLoaded", function() {
    const aiAutofillBtn = document.getElementById("ai-autofill-btn");
    if (aiAutofillBtn) {
        aiAutofillBtn.addEventListener("click", async function() {
            const files = getSelectedImageFiles();
            if (files.length === 0) return;
            
            aiAutofillBtn.disabled = true;
            aiAutofillBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> AI đang phân tích...';
            
            const formData = new FormData();
            formData.append("image", files[0]);
            
            try {
                const res = await fetch("api/ai_autofill.php", {
                    method: "POST",
                    body: formData
                });
                const result = await res.json();
                
                if (result.success && result.data) {
                    const data = result.data;
                    
                    const fields = [
                        { id: 'tieude', value: data.tieude },
                        { id: 'gia', value: data.gia },
                        { id: 'dientich', value: data.dientich },
                        { id: 'mota', value: data.mota }
                    ];
                    
                    fields.forEach(f => {
                        const el = document.getElementById(f.id);
                        if (el) {
                            el.value = f.value;
                            el.dispatchEvent(new Event('input'));
                            
                            // Hiệu ứng phát sáng
                            el.style.boxShadow = "0 0 15px rgba(16, 185, 129, 0.6)";
                            el.style.borderColor = "#10b981";
                            setTimeout(() => {
                                el.style.boxShadow = "";
                                el.style.borderColor = "";
                            }, 2000);
                        }
                    });
                    
                    // Tự chọn checkbox tiện nghi
                    if (Array.isArray(data.tiennghi)) {
                        document.querySelectorAll('.amenity-checkbox input').forEach(input => {
                            const isMatch = data.tiennghi.some(val => 
                                val.toLowerCase().trim() === input.value.toLowerCase().trim()
                            );
                            input.checked = isMatch;
                            input.dispatchEvent(new Event('change'));
                        });
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công!',
                        text: 'Trợ lý AI đã phân tích hình ảnh và điền thông tin bài đăng.',
                        confirmButtonColor: '#10b981',
                        timer: 2000
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi phân tích',
                        text: result.message || 'AI không nhận dạng được hình ảnh này.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch(e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi kết nối',
                    text: 'Không thể kết nối đến máy chủ AI.',
                    confirmButtonColor: '#ef4444'
                });
            } finally {
                aiAutofillBtn.disabled = false;
                aiAutofillBtn.innerHTML = '<i class="fas fa-magic"></i> Điền nhanh bằng AI ✨';
            }
        });
    }
});

function setSelectedImages(fileList, options) {
    var opts = options || {};
    var previousCount = selectedImageFiles.length;
    var files = opts.append ? mergeImageFiles(fileList) : Array.from(fileList || []);
    if (!files.length) {
        selectedImageFiles = [];
        syncImageInputState();
        renderImagePreviews();
        fileInput.value = '';
        return true;
    }

    if (files.length > 5) {
        Swal.fire({
            icon: 'warning',
            title: 'Quá số lượng ảnh',
            text: 'Bạn chỉ được chọn tối đa 5 ảnh.',
            confirmButtonColor: '#764ba2'
        });
        syncImageInputState();
        return false;
    }

    for (var i = 0; i < files.length; i++) {
        if (!imageTypeAllowed(files[i])) {
            Swal.fire({
                icon: 'warning',
                title: 'Định dạng không hợp lệ',
                text: 'Chỉ hỗ trợ ảnh JPG, PNG, GIF, WEBP.',
                confirmButtonColor: '#764ba2'
            });
            syncImageInputState();
            return false;
        }
    }

    selectedImageFiles = files;
    syncImageInputState();
    renderImagePreviews();

    if (previousCount === 0 && selectedImageFiles.length > 0) {
        setTimeout(function() {
            Swal.fire({
                title: 'Trợ lý AI Mái Nhà Xanh ✨',
                text: 'Bạn vừa tải lên ảnh phòng trọ. Bạn có muốn sử dụng AI để tự động phân tích hình ảnh và điền nhanh toàn bộ thông tin bài viết (tiêu đề, giá, diện tích, mô tả, tiện nghi) không?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Có, điền nhanh bằng AI! ✨',
                cancelButtonText: 'Không, tôi tự điền'
            }).then((result) => {
                if (result.isConfirmed) {
                    const aiBtn = document.getElementById('ai-autofill-btn');
                    if (aiBtn) aiBtn.click();
                }
            });
        }, 300);
    }

    return true;
}

// Paste images from clipboard
window.addEventListener('paste', function(e) {
    // Only handle paste if we are on the post page and not in a critical focus state if needed,
    // but usually, pasting an image is intentional.
    if (e.clipboardData && e.clipboardData.files && e.clipboardData.files.length > 0) {
        var files = Array.from(e.clipboardData.files);
        var imageFiles = files.filter(function(f) { return f.type.startsWith('image/'); });
        
        if (imageFiles.length > 0) {
            e.preventDefault();
            setSelectedImages(imageFiles, { append: getSelectedImageFiles().length > 0 });
        }
    }
});

uploadArea.addEventListener('click', function(e) {
    if (removeBtn.contains(e.target)) {
        return;
    }
    fileInput.value = '';
    fileInput.click();
});

fileInput.addEventListener('change', function() {
    setSelectedImages(this.files, { append: getSelectedImageFiles().length > 0 });
});

removeBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    setSelectedImages([]);
});

// Drag and drop
uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = 'var(--primary-color)';
    this.style.background = 'rgba(118, 75, 162, 0.05)';
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.style.borderColor = '#d0d0d0';
    this.style.background = '#fafafa';
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '#d0d0d0';
    this.style.background = '#fafafa';
    if (e.dataTransfer.files.length > 0) {
        setSelectedImages(e.dataTransfer.files, { append: getSelectedImageFiles().length > 0 });
    }
});

// Video Upload Preview
var videoUploadArea = document.getElementById('videoUploadArea');
var videoInput = document.getElementById('video');
var videoPlaceholder = document.getElementById('videoPlaceholder');
var videoPreview = document.getElementById('videoPreview');
var previewVid = document.getElementById('previewVid');
var removeVideoBtn = document.getElementById('removeVideo');

videoUploadArea.addEventListener('click', function() { videoInput.click(); });

videoInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        previewVid.src = URL.createObjectURL(this.files[0]);
        videoPlaceholder.style.display = 'none';
        videoPreview.style.display = 'block';
    }
});

removeVideoBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    videoInput.value = '';
    previewVid.src = '';
    videoPreview.style.display = 'none';
    videoPlaceholder.style.display = 'block';
});

videoUploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = 'var(--primary-color)';
});
videoUploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.style.borderColor = '#d0d0d0';
});
videoUploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '#d0d0d0';
    if (e.dataTransfer.files.length > 0) {
        videoInput.files = e.dataTransfer.files;
        videoInput.dispatchEvent(new Event('change'));
    }
});

// Form Submit
document.getElementById('postRoomForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    var selectedImages = getSelectedImageFiles();

    if (selectedImages.length < 1) {
        Swal.fire({
            icon: 'warning',
            title: 'Thiếu hình ảnh',
            text: 'Bài đăng bắt buộc phải có ít nhất 1 ảnh.',
            confirmButtonColor: '#764ba2'
        });
        return;
    }
    if (selectedImages.length > 5) {
        Swal.fire({
            icon: 'warning',
            title: 'Quá số lượng ảnh',
            text: 'Bạn chỉ được chọn tối đa 5 ảnh.',
            confirmButtonColor: '#764ba2'
        });
        return;
    }
    
    var submitBtn = this.querySelector('.btn-submit-post');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';
    
    var formData = new FormData(this);
    formData.delete('hinhanh[]');
    formData.delete('hinhanh');
    selectedImages.forEach(function(file) {
        formData.append('hinhanh[]', file, file.name);
    });
    
    try {
        var response = await fetch('api/dangbai.php', {
            method: 'POST',
            body: formData
        });
        
        var result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: result.message,
                confirmButtonText: 'OK',
                confirmButtonColor: '#764ba2'
            }).then(function() {
                // Reset form
                document.getElementById('postRoomForm').reset();
                setSelectedImages([]);
                previewVid.src = '';
                videoPreview.style.display = 'none';
                videoPlaceholder.style.display = 'block';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi!',
                text: result.message,
                confirmButtonText: 'OK',
                confirmButtonColor: '#764ba2'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi!',
            text: 'Không thể kết nối đến server. Vui lòng thử lại!',
            confirmButtonText: 'OK',
            confirmButtonColor: '#764ba2'
        });
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Đăng bài';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
