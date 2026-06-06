<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/media_helper.php';
require_once '../includes/room_status_helper.php';
requireLogin('admin');

$database = new Database();
$db = $database->getConnection();
ensureDangbaiRoomStatusSchema($db);

try { $db->exec("UPDATE dangbai_chothuetro SET admin_seen = 1 WHERE admin_seen = 0"); } catch (Exception $e) {}

// Filters
$filter_status  = trim($_GET['status'] ?? '');
$filter_keyword = trim($_GET['keyword'] ?? '');
$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset= ($page-1)*$limit;

// Build WHERE
$where = ['1=1'];
$params = [];
if ($filter_status && in_array($filter_status, ['cho_duyet','da_duyet','tu_choi'])) {
    $where[] = 'trangthai = :status';
    $params[':status'] = $filter_status;
}
if ($filter_keyword) {
    $where[] = '(tieude LIKE :kw OR diachi LIKE :kw2 OR nguoidang LIKE :kw3)';
    $params[':kw']  = "%$filter_keyword%";
    $params[':kw2'] = "%$filter_keyword%";
    $params[':kw3'] = "%$filter_keyword%";
}
$whereStr = implode(' AND ', $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM dangbai_chothuetro WHERE $whereStr");
foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
$countStmt->execute();
$total = $countStmt->fetchColumn();
$pages = ceil($total / $limit);

// Fetch
$stmt = $db->prepare("
    SELECT * FROM dangbai_chothuetro
    WHERE $whereStr
    ORDER BY ngaydang DESC, id DESC
    LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats for tabs
$stats_stmt = $db->query("
    SELECT trangthai, COUNT(*) as cnt FROM dangbai_chothuetro GROUP BY trangthai
");
$raw = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$tab_counts = [
    'all'       => array_sum($raw),
    'cho_duyet' => $raw['cho_duyet'] ?? 0,
    'da_duyet'  => $raw['da_duyet']  ?? 0,
    'tu_choi'   => $raw['tu_choi']   ?? 0,
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duyệt bài đăng — Admin Mái Nhà Xanh</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;">
            <button class="mobile-menu-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
            <div class="topbar-title">Phòng trọ <span>Duyệt & Quản lý</span></div>
        </div>
        <div class="topbar-right" style="display:flex;align-items:center;gap:16px;">
            <span style="font-size:.85rem;color:var(--text-muted);"><?= date('d/m/Y H:i') ?></span>
            <?php if(!empty($_SESSION['avatar'])): ?><img src="../<?= htmlspecialchars($_SESSION['avatar']) ?>" class="admin-avatar" style="object-fit:cover;" alt="Avatar"><?php else: ?><div class="admin-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="admin-content">

        <div style="display:flex;gap:15px;margin-bottom:30px;flex-wrap:wrap;">
            <?php
            $tabs = [
                '' => ['label'=>'Tất cả', 'count'=>$tab_counts['all'], 'color'=>'var(--info)'],
                'cho_duyet' => ['label'=>'Chờ duyệt', 'count'=>$tab_counts['cho_duyet'], 'color'=>'var(--warning)'],
                'da_duyet'  => ['label'=>'Đã duyệt',  'count'=>$tab_counts['da_duyet'],  'color'=>'var(--accent)'],
                'tu_choi'   => ['label'=>'Từ chối',   'count'=>$tab_counts['tu_choi'],   'color'=>'var(--danger)'],
            ];
            foreach ($tabs as $val => $t):
                $isActive = ($filter_status === $val);
                $btnClass = $isActive ? 'btn-active' : '';
            ?>
            <a href="?status=<?= $val ?>&keyword=<?= urlencode($filter_keyword) ?>" 
               class="btn btn-outline <?= $btnClass ?>" 
               style="min-width:140px; <?= $isActive ? "background: {$t['color']}; border-color: {$t['color']};" : "" ?>">
                <?= $t['label'] ?>
                <span class="nav-badge" style="background:rgba(255,255,255,0.2); color:<?= $isActive ? '#fff' : 'inherit' ?>; position:static; margin-left:10px; font-weight:800;"><?= $t['count'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- TABLE CARD -->
        <div class="card">
            <!-- SEARCH BAR -->
            <form class="filter-bar" method="GET" action="" style="padding: 20px 24px; border:none; border-bottom: 1px solid var(--glass-green-border-soft);">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <div class="search-box" style="position:relative; flex:1;">
                    <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;"></i>
                    <input type="text" name="keyword" value="<?= htmlspecialchars($filter_keyword) ?>"
                           placeholder="Tìm tên phòng, địa chỉ hoặc người đăng..."
                           class="filter-input" style="width:100%; padding-left:38px;">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Lọc kết quả</button>
                <?php if($filter_keyword): ?>
                <a href="?status=<?= $filter_status ?>" class="btn btn-outline">Xoá lọc</a>
                <?php endif; ?>
            </form>

            <?php if (!empty($posts)): ?>
            <div class="bulk-action-bar" style="padding: 12px 24px; border-bottom: 1px solid var(--glass-green-border-soft); display:flex; align-items:center; justify-content:space-between;">
                <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer;color:var(--text-muted);">
                    <input type="checkbox" id="postSelectAll" onchange="togglePostSelectAll(this)" style="width:18px;height:18px;accent-color:var(--accent);">
                    Chọn tất cả bài đăng
                </label>
                <div class="bulk-btns" style="display:flex;gap:10px;">
                    <?php if ($filter_status !== 'da_duyet'): ?>
                    <button class="btn btn-primary btn-sm" id="postBulkApproveBtn" style="display:none;" onclick="bulkApprovePosts()">
                        <i class="fas fa-check-circle"></i> Duyệt nhanh (<span id="postApproveCount">0</span>)
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-danger btn-sm" id="postBulkDeleteBtn" style="display:none;" onclick="bulkDeletePosts()">
                        <i class="fas fa-trash-alt"></i> Xoá vĩnh viễn (<span id="postSelectedCount">0</span>)
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($posts)): ?>
            <div class="empty-state">
                <i class="fas fa-search-minus" style="font-size:3rem; opacity:.2;"></i>
                <h4>Không tìm thấy bài đăng</h4>
                <p>Vui lòng kiểm tra lại bộ lọc hoặc từ khóa tìm kiếm.</p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px; text-align:center;">#</th>
                            <th style="width:50px;">ID</th>
                            <th>Thông tin phòng</th>
                            <th>Giá & Diện tích</th>
                            <th>Vị trí</th>
                            <th>Người đăng</th>
                            <th>Trạng thái</th>
                            <th style="text-align:right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($posts as $i => $p): ?>
                        <tr id="row-<?= $p['id'] ?>" class="<?= $p['trangthai']==='cho_duyet' ? 'row-highlight' : '' ?>">
                            <td style="text-align:center;">
                                <input type="checkbox" class="post-row-check" value="<?= $p['id'] ?>" data-status="<?= $p['trangthai'] ?>" onchange="onPostCheckChange()" style="width:17px;height:17px;accent-color:var(--accent);">
                            </td>
                            <td style="color:var(--text-muted);font-size:.8rem;">#<?= $p['id'] ?></td>
                            <td>
                                <div style="display:flex;gap:12px;align-items:center;">
                                    <?php if ($p['hinhanh']): ?>
                                    <img src="<?= htmlspecialchars(buildMediaUrl($p['hinhanh'] ?? '', '..')) ?>" class="room-thumb" style="width:60px;height:45px;border-radius:6px;object-fit:cover;" onerror="this.onerror=null; this.src='../assets/images/myhome.png';">
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight:700;color:var(--text);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($p['tieude']) ?></div>
                                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px;"><i class="fas fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($p['ngaydang'])) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:700;color:var(--accent-dark);"><?= number_format($p['gia']) ?> ₫</div>
                                <div style="font-size:.78rem;color:var(--text-muted);"><?= $p['dientich'] ?> m²</div>
                            </td>
                            <td style="font-size:.85rem;color:var(--text-muted);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($p['diachi']) ?></td>
                            <td style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($p['nguoidang'] ?? '—') ?></td>
                            <td id="badge-<?= $p['id'] ?>">
                                <?php if ($p['trangthai']==='cho_duyet'): ?>
                                    <span class="badge badge-warning"><i class="fas fa-clock"></i> Chờ duyệt</span>
                                <?php elseif($p['trangthai']==='da_duyet'): ?>
                                    <span class="badge badge-approved"><i class="fas fa-check-circle"></i> Đã duyệt</span>
                                <?php else: ?>
                                    <span class="badge badge-rejected"><i class="fas fa-times-circle"></i> Từ chối</span>
                                <?php endif; ?>

                                <?php
                                $aiCheck = null;
                                if (!empty($p['ai_check'])) {
                                    $aiCheck = json_decode($p['ai_check'], true);
                                }
                                ?>
                                <?php if ($aiCheck): ?>
                                    <div style="margin-top: 6px; white-space: nowrap;">
                                        <?php if ($aiCheck['verdict'] === 'SAFE'): ?>
                                            <span style="font-size:0.75rem; color:#16a34a; font-weight:700;" title="<?= htmlspecialchars($aiCheck['details']) ?>">
                                                <i class="fas fa-shield-alt"></i> AI: Duyệt (<?= $aiCheck['risk_score'] ?>%)
                                            </span>
                                        <?php elseif ($aiCheck['verdict'] === 'WARNING'): ?>
                                            <span style="font-size:0.75rem; color:#d97706; font-weight:700;" title="<?= htmlspecialchars($aiCheck['details']) ?>">
                                                <i class="fas fa-exclamation-triangle"></i> AI: Cảnh báo (<?= $aiCheck['risk_score'] ?>%)
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size:0.75rem; color:#dc2626; font-weight:700;" title="<?= htmlspecialchars($aiCheck['details']) ?>">
                                                <i class="fas fa-times-circle"></i> AI: Từ chối (<?= $aiCheck['risk_score'] ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top: 6px;">
                                        <button class="btn btn-outline btn-xs" style="padding: 2px 6px; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 3px;" onclick="runAiCheckInline(<?= $p['id'] ?>, event)" title="Chạy AI kiểm duyệt tin đăng này">
                                            <i class="fas fa-robot"></i> Kiểm duyệt AI
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex;gap:6px;justify-content:flex-end;">
                                    <button class="btn btn-xs btn-outline" title="Xem chi tiết" onclick="viewPost(<?= htmlspecialchars(json_encode(array_merge($p, ['hinhanh_preview' => buildMediaUrl($p['hinhanh'] ?? '', '..')]))) ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($p['trangthai'] !== 'da_duyet'): ?>
                                    <button class="btn btn-xs btn-primary" title="Duyệt bài" onclick="handlePost(<?= $p['id'] ?>, 'approve')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($p['trangthai'] !== 'tu_choi'): ?>
                                    <button class="btn btn-xs btn-danger" title="Từ chối" onclick="openRejectModal(<?= $p['id'] ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-xs btn-danger" title="Xoá vĩnh viễn" onclick="confirmDelete(<?= $p['id'] ?>)">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&status=<?= $filter_status ?>&keyword=<?= urlencode($filter_keyword) ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($pi = 1; $pi <= $pages; $pi++): ?>
                    <?php if ($pi == 1 || $pi == $pages || ($pi >= $page - 1 && $pi <= $page + 1)): ?>
                    <a href="?page=<?= $pi ?>&status=<?= $filter_status ?>&keyword=<?= urlencode($filter_keyword) ?>" class="page-btn <?= $pi===$page ? 'active' : '' ?>"><?= $pi ?></a>
                    <?php elseif ($pi == 2 || $pi == $pages - 1): ?>
                    <span style="padding:0 5px; color:var(--text-muted);">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                <a href="?page=<?= $page+1 ?>&status=<?= $filter_status ?>&keyword=<?= urlencode($filter_keyword) ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- VIEW POST MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-box" style="max-width:750px;">
        <div class="modal-header" style="background:linear-gradient(to right, #f8fafc, #fff);">
            <h3><i class="fas fa-info-circle" style="color:var(--accent)"></i> Chi tiết bài đăng #<span id="viewPostId"></span></h3>
            <button class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="viewModalBody" style="padding:24px;"></div>
        <div class="modal-footer" id="viewModalFooter" style="background:#f8fafc;"></div>
    </div>
</div>

<!-- REJECT MODAL -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box" style="max-width:450px;">
        <div class="modal-header">
            <h3><i class="fas fa-ban" style="color:var(--danger)"></i> Lý do từ chối</h3>
            <button class="modal-close" onclick="closeModal('rejectModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rejectId">
            <textarea id="rejectNote" rows="4" class="filter-input" style="width:100%; border-radius:10px; padding:15px;" placeholder="Ví dụ: Ảnh mờ, thông tin không chính xác..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('rejectModal')">Quay lại</button>
            <button class="btn btn-danger" onclick="confirmReject()">Xác nhận từ chối</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
// --- Real-time feedback using Swal & Custom Toasts ---
function showToast(msg, type='success') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: msg,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

function togglePostSelectAll(cb) {
    document.querySelectorAll('.post-row-check').forEach(c => c.checked = cb.checked);
    onPostCheckChange();
}

function onPostCheckChange() {
    const count = document.querySelectorAll('.post-row-check:checked').length;
    const delBtn = document.getElementById('postBulkDeleteBtn');
    const apprBtn = document.getElementById('postBulkApproveBtn');
    
    if (document.getElementById('postSelectedCount')) document.getElementById('postSelectedCount').textContent = count;
    if (document.getElementById('postApproveCount')) document.getElementById('postApproveCount').textContent = count;
    
    if (delBtn) delBtn.style.display = count > 0 ? 'inline-flex' : 'none';
    if (apprBtn) apprBtn.style.display = count > 0 ? 'inline-flex' : 'none';
}

async function bulkDeletePosts() {
    const checks = document.querySelectorAll('.post-row-check:checked');
    const result = await Swal.fire({
        title: `Xoá ${checks.length} bài đăng?`,
        text: "Dữ liệu sẽ bị xoá vĩnh viễn và không thể khôi phục!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Xoá ngay',
        cancelButtonText: 'Huỷ'
    });

    if (result.isConfirmed) {
        const fd = new FormData();
        fd.append('action', 'delete_many');
        checks.forEach(c => fd.append('ids[]', c.value));
        const res = await fetch('../api/admin_posts.php', { 
            method: 'POST', 
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: fd 
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire('Thành công', data.message, 'success').then(() => location.reload());
        }
    }
}

async function bulkApprovePosts() {
    const checks = document.querySelectorAll('.post-row-check:checked');
    const result = await Swal.fire({
        title: `Duyệt nhanh ${checks.length} bài?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Duyệt tất cả',
        cancelButtonText: 'Huỷ'
    });

    if (result.isConfirmed) {
        const fd = new FormData();
        fd.append('action', 'approve_many');
        checks.forEach(c => fd.append('ids[]', c.value));
        const res = await fetch('../api/admin_posts.php', { 
            method: 'POST', 
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: fd 
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire('Đã duyệt', data.message, 'success').then(() => location.reload());
        }
    }
}

async function confirmDelete(id) {
    const result = await Swal.fire({
        title: 'Xoá vĩnh viễn?',
        text: "Bài đăng này sẽ biến mất hoàn toàn khỏi hệ thống!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Đồng ý xoá'
    });
    if (result.isConfirmed) handlePost(id, 'delete');
}

async function handlePost(id, action, note='') {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('action', action);
    fd.append('note', note);

    try {
        const res  = await fetch('../api/admin_posts.php', {
            method:'POST', 
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body:fd
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message);
            if (action === 'delete') {
                document.getElementById('row-' + id)?.remove();
            } else {
                // Refresh inline or reload
                setTimeout(() => location.reload(), 1000);
            }
            closeModal('viewModal');
            closeModal('rejectModal');
        } else {
            Swal.fire('Lỗi', data.message, 'error');
        }
    } catch(e) { showToast('Lỗi kết nối API', 'error'); }
}

function openRejectModal(id) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectNote').value = '';
    document.getElementById('rejectModal').classList.add('open');
}

function confirmReject() {
    const id = document.getElementById('rejectId').value;
    const note = document.getElementById('rejectNote').value;
    handlePost(id, 'reject', note);
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

async function runAiCheckInline(id, e) {
    if (e) e.stopPropagation();
    Swal.fire({
        title: 'Đang kiểm duyệt bằng AI...',
        text: 'Vui lòng chờ Gemini phân tích hình ảnh và bài đăng.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const fd = new FormData();
    fd.append('id', id);
    fd.append('action', 'run_ai_check');

    try {
        const res = await fetch('../api/admin_posts.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire('Thành công', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Lỗi', data.message, 'error');
        }
    } catch(err) {
        Swal.fire('Lỗi', 'Lỗi kết nối API', 'error');
    }
}

function viewPost(post) {
    document.getElementById('viewPostId').textContent = post.id;
    const body = document.getElementById('viewModalBody');
    const footer = document.getElementById('viewModalFooter');
    
    const statusBadges = {
        'cho_duyet': '<span class="badge badge-warning">Chờ duyệt</span>',
        'da_duyet':  '<span class="badge badge-approved">Đã duyệt</span>',
        'tu_choi':   '<span class="badge badge-rejected">Từ chối</span>',
    };

    let aiHtml = '';
    if (post.ai_check) {
        let aiData = null;
        try {
            aiData = typeof post.ai_check === 'string' ? JSON.parse(post.ai_check) : post.ai_check;
        } catch(e) { console.error(e); }

        if (aiData && aiData.verdict) {
            let verdictLabel = '';
            let verdictIcon = '';
            let riskColor = '';
            let cardBg = '';
            let cardBorder = '';

            if (aiData.verdict === 'SAFE') {
                verdictLabel = 'An toàn (Đề xuất Duyệt)';
                verdictIcon = '<i class="fas fa-shield-alt"></i>';
                riskColor = '#16a34a';
                cardBg = 'rgba(22, 163, 74, 0.05)';
                cardBorder = 'rgba(22, 163, 74, 0.2)';
            } else if (aiData.verdict === 'WARNING') {
                verdictLabel = 'Cảnh báo (Cần lưu ý)';
                verdictIcon = '<i class="fas fa-exclamation-triangle"></i>';
                riskColor = '#d97706';
                cardBg = 'rgba(217, 119, 6, 0.05)';
                cardBorder = 'rgba(217, 119, 6, 0.2)';
            } else {
                verdictLabel = 'Nguy hiểm (Khuyên Từ chối)';
                verdictIcon = '<i class="fas fa-times-circle"></i>';
                riskColor = '#dc2626';
                cardBg = 'rgba(220, 38, 38, 0.05)';
                cardBorder = 'rgba(220, 38, 38, 0.2)';
            }

            const reasonsLi = (aiData.reasons || []).map(r => `<li style="display:flex; align-items:start; gap:8px; margin-bottom:4px; text-align:left;"><i class="fas fa-check-circle" style="color:${riskColor}; margin-top:3px; font-size:0.8rem;"></i><span>${r}</span></li>`).join('');

            aiHtml = `
                <div class="ai-analysis-card" style="margin-bottom:20px; padding:18px; border-radius:12px; display:flex; flex-direction:column; gap:12px; background:${cardBg}; border:1px solid ${cardBorder}; text-align: left;">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px dashed rgba(0,0,0,0.1); padding-bottom:10px;">
                        <span style="font-weight:700; font-size:1rem; display:flex; align-items:center; gap:8px; color:${riskColor};">
                            ${verdictIcon} KIỂM DUYỆT AI: ${verdictLabel}
                        </span>
                        <div style="text-align:right;">
                            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Điểm rủi ro</div>
                            <div style="font-size:1.2rem; font-weight:800; color:${riskColor};">${aiData.risk_score}%</div>
                        </div>
                    </div>
                    
                    <div style="background:rgba(0,0,0,0.05); height:8px; border-radius:4px; overflow:hidden; position:relative;">
                        <div style="background:${riskColor}; width:${aiData.risk_score}%; height:100%; border-radius:4px;"></div>
                    </div>
                    
                    <div>
                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-bottom:6px;">Lý do chi tiết</div>
                        <ul style="list-style:none; margin:0; padding:0; font-size:0.85rem; line-height:1.5; color:var(--text);">
                            ${reasonsLi}
                        </ul>
                    </div>
                    
                    ${aiData.details ? `
                    <div style="font-size:0.82rem; color:var(--text-muted); border-top:1px dashed rgba(0,0,0,0.05); padding-top:8px;">
                        <strong>Nhận xét:</strong> ${aiData.details}
                    </div>
                    ` : ''}

                    ${post.trangthai === 'cho_duyet' ? `
                    <div style="display:flex; align-items:center; gap:10px; font-size:0.85rem; margin-top:4px; padding-top:8px; border-top:1px dashed rgba(0,0,0,0.05);">
                        <i class="fas fa-magic" style="color:var(--accent);"></i>
                        <span style="color:var(--text-muted);">Muốn kiểm duyệt lại?</span>
                        <button class="btn btn-outline btn-xs" onclick="runAiCheckInline(${post.id}, event)" style="padding: 2px 8px; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;">
                            <i class="fas fa-sync-alt"></i> Phân tích lại
                        </button>
                    </div>
                    ` : ''}
                </div>
            `;
        }
    } else {
        aiHtml = `
            <div class="ai-analysis-card" style="margin-bottom:20px; padding:18px; border-radius:12px; background:rgba(0,0,0,0.02); border:1px solid rgba(0,0,0,0.08); display:flex; justify-content:space-between; align-items:center; gap:16px; text-align: left;">
                <div>
                    <h5 style="margin:0 0 4px 0; font-weight:700; color:var(--text);"><i class="fas fa-robot" style="color:var(--accent);"></i> Bài viết chưa được AI kiểm duyệt</h5>
                    <p style="margin:0; font-size:0.8rem; color:var(--text-muted);">Chạy phân tích AI để tự động phát hiện rủi ro và nhận diện nội dung vi phạm.</p>
                </div>
                <button class="btn btn-primary btn-sm" onclick="runAiCheckInline(${post.id}, event)" style="flex-shrink:0;">
                    <i class="fas fa-bolt"></i> Kiểm duyệt AI
                </button>
            </div>
        `;
    }
    
    body.innerHTML = `
        <div style="display:grid; grid-template-columns: 280px 1fr; gap:24px;">
            <div style="position:sticky; top:0;">
                <img src="${post.hinhanh_preview}" style="width:100%; border-radius:12px; box-shadow:0 10px 20px rgba(0,0,0,0.1);" onerror="this.onerror=null; this.src='../assets/images/myhome.png';">
                <div style="margin-top:16px; padding:15px; background:#f0fdf4; border-radius:10px; border:1px solid #dcfce7;">
                    <div style="font-size:.75rem; color:#15803d; font-weight:700; text-transform:uppercase; margin-bottom:8px;">Thông tin giá</div>
                    <div style="font-size:1.4rem; font-weight:800; color:#166534;">${Number(post.gia).toLocaleString('vi-VN')} ₫</div>
                    <div style="font-size:.85rem; color:#15803d; margin-top:2px;">Diện tích: ${post.dientich} m²</div>
                </div>
            </div>
            <div>
                <div style="margin-bottom:12px;">${statusBadges[post.trangthai] || ''}</div>
                <h2 style="font-size:1.5rem; font-weight:800; line-height:1.3; margin-bottom:16px; color:var(--text);">${post.tieude}</h2>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:24px; padding:16px; background:#f8fafc; border-radius:12px;">
                    <div>
                        <div style="font-size:.7rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Vị trí</div>
                        <div style="font-size:.9rem; font-weight:600;"><i class="fas fa-map-marker-alt" style="color:var(--accent);"></i> ${post.diachi}</div>
                    </div>
                    <div>
                        <div style="font-size:.7rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Liên hệ chủ nhà</div>
                        <div style="font-size:.9rem; font-weight:600;"><i class="fas fa-phone-alt" style="color:var(--accent);"></i> ${post.sdt_chunha || '—'}</div>
                    </div>
                    <div>
                        <div style="font-size:.7rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Ngày đăng</div>
                        <div style="font-size:.9rem; font-weight:600;"><i class="fas fa-calendar" style="color:var(--accent);"></i> ${new Date(post.ngaydang).toLocaleDateString('vi-VN')}</div>
                    </div>
                    <div>
                        <div style="font-size:.7rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Người đăng</div>
                        <div style="font-size:.9rem; font-weight:600;"><i class="fas fa-user-circle" style="color:var(--accent);"></i> ${post.nguoidang || '—'}</div>
                    </div>
                </div>

                ${aiHtml}

                <div style="margin-bottom:20px;">
                    <div style="font-size:.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-bottom:8px;">Mô tả chi tiết</div>
                    <p style="font-size:.95rem; line-height:1.6; color:#475569;">${post.mota || 'Không có mô tả.'}</p>
                </div>
            </div>
        </div>
    `;
    
    let btns = `<button class="btn btn-outline" onclick="closeModal('viewModal')">Đóng</button>`;
    if (post.trangthai !== 'da_duyet') {
        btns = `<button class="btn btn-primary" onclick="handlePost(${post.id},'approve')"><i class="fas fa-check"></i> Duyệt ngay</button>` + btns;
    }
    if (post.trangthai !== 'tu_choi') {
        btns = `<button class="btn btn-danger" onclick="openRejectModal(${post.id});closeModal('viewModal')"><i class="fas fa-times"></i> Từ chối</button>` + btns;
    }
    footer.innerHTML = btns;
    document.getElementById('viewModal').classList.add('open');
}
</script>
</body>
</html>
