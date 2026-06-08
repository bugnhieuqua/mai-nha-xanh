const totalRooms = document.querySelectorAll('.room-card').length;

let currentLimit = window.innerWidth <= 992 ? 6 : 12;
let filteredRooms = [];

// Chuẩn hóa chuỗi: bỏ dấu tiếng Việt, thường hóa (hỗ trợ tìm không dấu)
function normalizeVN(str) {
    if (!str) return '';
    var res = str.toLowerCase();
    res = res.replace(/[àáạảãâầấậẩẫăằắặẳẵ]/g, "a");
    res = res.replace(/[èéẹẻẽêềếệểễ]/g, "e");
    res = res.replace(/[ìíịỉĩ]/g, "i");
    res = res.replace(/[òóọỏõôồốộổỗơờớợởỡ]/g, "o");
    res = res.replace(/[ùúụủũưừứựửữ]/g, "u");
    res = res.replace(/[ỳýỵỷỹ]/g, "y");
    res = res.replace(/đ/g, "d");
    res = res.normalize('NFD').replace(/[\u0300-\u036f]/g, "");
    return res.trim();
}

// ===== Listbox địa điểm =====
function showLocationList() {
    const lb = document.getElementById('locationListbox');
    if (lb) lb.style.display = 'block';
}

function filterLocationList(val) {
    const norm = normalizeVN(val);
    const items = document.querySelectorAll('#locationListbox li');
    let any = false;
    items.forEach(li => {
        const liNorm = normalizeVN(li.dataset.norm || li.textContent);
        const visible = !norm || liNorm.includes(norm);
        li.style.display = visible ? '' : 'none';
        if (visible) any = true;
    });
    const lb = document.getElementById('locationListbox');
    if (lb) lb.style.display = any ? 'block' : 'none';
    // Khi xóa hết input → reset filter ngay
    if (!val.trim()) {
        document.getElementById('locationFilter').value = '';
        const clearBtn = document.getElementById('locationClearBtn');
        if (clearBtn) clearBtn.style.display = 'none';
        applyFilters();
    }
}

function selectLocation(val, label) {
    console.log("Selected location:", val);
    document.getElementById('locationFilter').value = val;
    document.getElementById('locationInput').value  = val ? label : '';
    const clearBtn = document.getElementById('locationClearBtn');
    if (clearBtn) clearBtn.style.display = val ? 'inline' : 'none';
    
    const lb = document.getElementById('locationListbox');
    if (lb) lb.style.display = 'none';
    
    const chevron = document.getElementById('locationChevron');
    if (chevron) chevron.style.transform = 'translateY(-50%)';
    
    const wrapper = document.getElementById('locationWrapper');
    if (wrapper) {
        wrapper.style.borderColor = val ? '#10b981' : '#d1d5db';
        wrapper.style.borderWidth = val ? '2px' : '1px';
    }
    applyFilters();
    if (window.updateMapMarkersList) {
        window.updateMapMarkersList(true);
    }
}

function clearLocationFilter() {
    document.getElementById('locationFilter').value = '';
    document.getElementById('locationInput').value  = '';
    const clearBtn = document.getElementById('locationClearBtn');
    if (clearBtn) clearBtn.style.display = 'none';
    const wrapper = document.getElementById('locationWrapper');
    if (wrapper) wrapper.style.borderColor = '#d1d5db';
    applyFilters();
    if (window.updateMapMarkersList) {
        window.updateMapMarkersList(true);
    }
}

function toggleLocationList() {
    const lb = document.getElementById('locationListbox');
    const chevron = document.getElementById('locationChevron');
    const isOpen = lb && lb.style.display === 'block';
    if (lb) lb.style.display = isOpen ? 'none' : 'block';
    if (chevron) chevron.style.transform = isOpen ? 'translateY(-50%)' : 'translateY(-50%) rotate(180deg)';
    if (!isOpen) document.getElementById('locationInput').focus();
}

// Ẩn listbox khi click ra ngoài
document.addEventListener('click', function(e) {
    if (!e.target.closest('.filter-group')) {
        const lb = document.getElementById('locationListbox');
        if (lb) lb.style.display = 'none';
    }
});

// ===== Bộ lọc chính =====
let prevSearch = '';
let prevPrice = '';
let prevArea = '';
let prevStatus = '';
let prevLocation = '';

function applyFilters() {
    const search         = normalizeVN(document.getElementById('searchInput').value || '');
    const priceFilter    = document.getElementById('priceFilter').value;
    const areaFilter     = document.getElementById('areaFilter').value;
    const statusFilter   = document.getElementById('statusFilter').value;
    const locationFilter = document.getElementById('locationFilter').value;
    const rooms          = document.querySelectorAll('.room-card');
    
    // Reset pagination to page 1 if any filter values changed
    if (search !== prevSearch || 
        priceFilter !== prevPrice || 
        areaFilter !== prevArea || 
        statusFilter !== prevStatus || 
        locationFilter !== prevLocation) {
        
        currentLimit = window.innerWidth <= 992 ? 6 : 12;
        
        prevSearch = search;
        prevPrice = priceFilter;
        prevArea = areaFilter;
        prevStatus = statusFilter;
        prevLocation = locationFilter;
    }
    
    filteredRooms = [];

    rooms.forEach(room => {
        const roomSearch   = normalizeVN(room.getAttribute('data-search') || '');
        const price        = parseFloat(room.getAttribute('data-price')) || 0;
        const area         = parseFloat(room.getAttribute('data-area')) || 0;
        const status       = room.getAttribute('data-status') || '';
        const roomLocation = (room.getAttribute('data-location') || '').toLowerCase();
        let show = true;

        if (search && !roomSearch.includes(search)) show = false;
        if (show && priceFilter) {
            const [min, max] = priceFilter.split('-').map(Number);
            if (price < min || price > max) show = false;
        }
        if (show && areaFilter) {
            const [min, max] = areaFilter.split('-').map(Number);
            if (area < min || area > max) show = false;
        }
        if (show && statusFilter && status !== statusFilter) show = false;
        if (show && locationFilter && roomLocation !== locationFilter) show = false;

        if (show) {
            filteredRooms.push(room);
        } else {
            room.style.display = 'none';
        }
    });

    let visible = 0;
    filteredRooms.forEach((room, index) => {
        if (index < currentLimit) {
            room.style.display = '';
            visible++;
        } else {
            room.style.display = 'none';
        }
    });

    const resultEl = document.getElementById('filterResult');
    const noResult = document.getElementById('no-filter-result');

    if (search || priceFilter || areaFilter || statusFilter || locationFilter) {
        if (resultEl) {
            resultEl.style.display = 'block';
            resultEl.textContent   = `Hiển thị ${visible}/${filteredRooms.length} phòng`;
        }
    } else {
        if (resultEl) resultEl.style.display = 'none';
    }

    if (filteredRooms.length === 0) {
        if (!noResult) {
            const p = document.createElement('p');
            p.id = 'no-filter-result';
            p.style.cssText = 'text-align:center;padding:40px;color:#888;font-size:1.1rem;grid-column:1/-1;';
            p.innerHTML = '😔 Không tìm thấy phòng trọ phù hợp. <a href="#" onclick="resetFilters();return false;" style="color:#10b981;">Xóa bộ lọc</a>';
            const rl = document.getElementById('roomsList');
            if (rl) rl.appendChild(p);
        }
    } else if (noResult) {
        noResult.remove();
    }

    updateLoadMoreBtn();
}

function updateLoadMoreBtn() {
    let btn = document.getElementById('loadMoreBtn');
    if (filteredRooms.length > currentLimit) {
        if (!btn) {
            btn = document.createElement('button');
            btn.id = 'loadMoreBtn';
            btn.className = 'btn-load-more';
            btn.style.cssText = 'display:block; margin: 30px auto; padding: 12px 24px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; grid-column: 1/-1;';
            btn.innerHTML = '<i class="fas fa-chevron-down"></i> Xem thêm';
            btn.onmouseover = () => btn.style.background = '#059669';
            btn.onmouseout = () => btn.style.background = '#10b981';
            btn.onclick = () => {
                currentLimit += window.innerWidth <= 992 ? 6 : 12;
                applyFilters();
            };
            const rl = document.getElementById('roomsList');
            if (rl) rl.appendChild(btn);
        }
        // Ensure it's at the end
        const rl = document.getElementById('roomsList');
        if (rl && btn) rl.appendChild(btn);
    } else {
        if (btn) btn.remove();
    }
}

function setPriceTag(el, val) {
    document.querySelectorAll('.price-tag').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('priceFilter').value = val;
    applyFilters();
}

function setAreaTag(el, val) {
    document.querySelectorAll('.area-tag').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('areaFilter').value = val;
    applyFilters();
}

function setStatusFilter(val) {
    document.getElementById('statusFilter').value = val;
    document.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
    const matched = document.querySelector(`.status-pill input[value="${val}"]`);
    if (matched) matched.closest('.status-pill').classList.add('active');
    applyFilters();
}

function resetFilters() {
    document.getElementById('searchInput').value    = '';
    document.getElementById('priceFilter').value    = '';
    document.getElementById('areaFilter').value     = '';
    document.getElementById('statusFilter').value   = '';
    document.getElementById('locationFilter').value = '';
    const li = document.getElementById('locationInput');
    if (li) li.value = '';
    const lb = document.getElementById('locationClearBtn');
    if (lb) lb.style.display = 'none';
    
    // Reset tag buttons
    document.querySelectorAll('.price-tag').forEach((b,i) => b.classList.toggle('active', i===0));
    document.querySelectorAll('.area-tag').forEach((b,i) => b.classList.toggle('active', i===0));
    document.querySelectorAll('.status-pill').forEach((b,i) => b.classList.toggle('active', i===0));
    
    const stAll = document.getElementById('st-all');
    if (stAll) stAll.checked = true;
    
    currentLimit = window.innerWidth <= 992 ? 6 : 12;
    applyFilters();
    if (window.updateMapMarkersList) {
        window.updateMapMarkersList(true);
    }
}

// ===== Mobile Filter Drawer Toggle =====
function toggleMobileFilter() {
    const sidebar = document.getElementById('filterSidebar');
    const overlay = document.getElementById('filterMobileOverlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    applyFilters();
});
