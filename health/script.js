// health/script.js

// ============================================
// متغیرها
// ============================================
let cycles = initialCycles || [];
let symptoms = initialSymptoms || [];
let predictionData = prediction || null;
let currentPhase = currentPhase || 'not_started';
let currentTab = 'dashboard';
let editCycleId = null;
let editSymptomId = null;

// ============================================
// توابع کمکی
// ============================================
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    setTimeout(() => toast.classList.add('show'), 50);
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function escapeHtml(text) {
    if (!text) return '';
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    let d = new Date(dateStr);
    let options = { year: 'numeric', month: 'long', day: 'numeric' };
    return d.toLocaleDateString('fa-IR', options);
}

function toPersianNumbers(str) {
    if (str === undefined || str === null) return '';
    let persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return String(str).replace(/[0-9]/g, function(d) { return persianDigits[parseInt(d)]; });
}

// ============================================
// مدیریت تب‌ها
// ============================================
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        let tabName = this.dataset.tab;
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('panel-' + tabName).classList.add('active');
        currentTab = tabName;

        if (tabName === 'dashboard') renderDashboard();
        if (tabName === 'cycles') renderCycles();
        if (tabName === 'symptoms') renderSymptoms();
        if (tabName === 'predict') renderPrediction();
    });
});

// ============================================
// رندر وضعیت فعلی
// ============================================
function renderCurrentStatus() {
    const phaseMap = {
        'menstruation': { icon: '🩸', text: 'قاعدگی', color: '#f5576c' },
        'follicular': { icon: '🌱', text: 'فولیکولی', color: '#667eea' },
        'ovulation': { icon: '🥚', text: 'تخمک‌گذاری', color: '#ffc107' },
        'luteal': { icon: '🌙', text: 'لوتئال', color: '#764ba2' },
        'pre_menstrual': { icon: '📌', text: 'پیش‌قاعدگی', color: '#ff6b6b' },
        'not_started': { icon: '📋', text: 'هنوز ثبت نشده', color: '#6c757d' }
    };

    let phase = phaseMap[currentPhase] || phaseMap['not_started'];
    document.getElementById('phaseIcon').textContent = phase.icon;
    document.getElementById('phaseText').textContent = phase.text;
    document.getElementById('phaseText').style.color = phase.color;

    let cyclesList = cycles.sort((a, b) => new Date(b.start_date) - new Date(a.start_date));

    if (cyclesList.length > 0) {
        let lastCycle = cyclesList[0];
        let lastStart = new Date(lastCycle.start_date);
        let today = new Date();
        let daysSinceStart = Math.floor((today - lastStart) / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('cycleDay').textContent = toPersianNumbers(daysSinceStart);
    } else {
        document.getElementById('cycleDay').textContent = '-';
    }

    if (predictionData) {
        document.getElementById('daysRemaining').textContent = toPersianNumbers(predictionData.days_until_next) + ' روز';
        document.getElementById('nextPeriod').textContent = formatDate(predictionData.next_period_start);
    } else {
        document.getElementById('daysRemaining').textContent = '-';
        document.getElementById('nextPeriod').textContent = '-';
    }

    // آمار
    document.getElementById('statCycles').textContent = toPersianNumbers(cycles.length);
    document.getElementById('statSymptoms').textContent = toPersianNumbers(symptoms.length);
    document.getElementById('statAvgCycle').textContent = predictionData ? toPersianNumbers(predictionData.avg_cycle_length) + ' روز' : '-';
}

// ============================================
// رندر داشبورد
// ============================================
function renderDashboard() {
    renderMiniCalendar();
    renderRecentSymptoms();
    renderPredictionSummary();
}

function renderMiniCalendar() {
    let container = document.getElementById('miniCalendar');
    let today = new Date();
    let year = today.getFullYear();
    let month = today.getMonth();
    let firstDay = new Date(year, month, 1);
    let lastDay = new Date(year, month + 1, 0);
    let daysInMonth = lastDay.getDate();
    let startDay = firstDay.getDay();

    let periodDays = [];
    cycles.forEach(cycle => {
        let start = new Date(cycle.start_date);
        let end = cycle.end_date ? new Date(cycle.end_date) : new Date(start.getTime() + 5 * 24 * 60 * 60 * 1000);
        let current = new Date(start);
        while (current <= end) {
            if (current.getMonth() === month && current.getFullYear() === year) {
                periodDays.push(current.getDate());
            }
            current.setDate(current.getDate() + 1);
        }
    });

    let fertileDays = [];
    if (predictionData && predictionData.fertile_window_start && predictionData.fertile_window_end) {
        let start = new Date(predictionData.fertile_window_start);
        let end = new Date(predictionData.fertile_window_end);
        let current = new Date(start);
        while (current <= end) {
            if (current.getMonth() === month && current.getFullYear() === year) {
                fertileDays.push(current.getDate());
            }
            current.setDate(current.getDate() + 1);
        }
    }

    let ovulationDay = null;
    if (predictionData && predictionData.ovulation_date) {
        let ov = new Date(predictionData.ovulation_date);
        if (ov.getMonth() === month && ov.getFullYear() === year) {
            ovulationDay = ov.getDate();
        }
    }

    let dayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
    let html = '<div class="mini-calendar">';
    dayNames.forEach(name => {
        html += '<div class="cal-header">' + name + '</div>';
    });

    for (let i = 0; i < startDay; i++) {
        html += '<div class="cal-day"></div>';
    }

    for (let d = 1; d <= daysInMonth; d++) {
        let cls = 'cal-day';
        if (d === today.getDate()) cls += ' today';
        if (periodDays.includes(d)) cls += ' period';
        if (fertileDays.includes(d)) cls += ' fertile';
        if (d === ovulationDay) cls += ' ovulation';
        html += '<div class="' + cls + '">' + toPersianNumbers(d) + '</div>';
    }

    html += '</div>';
    container.innerHTML = html;
}

function renderRecentSymptoms() {
    let container = document.getElementById('recentSymptoms');
    let sortedSymptoms = [...symptoms].sort((a, b) => new Date(b.date) - new Date(a.date));
    let recent = sortedSymptoms.slice(0, 5);

    if (recent.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted); text-align: center;">هنوز علائمی ثبت نشده است</p>';
        return;
    }

    container.innerHTML = recent.map(s => {
        let typeMap = {
            'pain': 'درد',
            'mood': 'خلق و خو',
            'physical': 'جسمی',
            'digestive': 'گوارشی',
            'other': 'سایر'
        };
        return '<div class="symptom-item">' +
            '<div class="symptom-info">' +
                '<span class="symptom-type">' + (typeMap[s.type] || s.type) + '</span>' +
                '<span class="symptom-severity ' + s.severity + '">' + (s.severity === 'low' ? 'کم' : s.severity === 'medium' ? 'متوسط' : 'زیاد') + '</span>' +
                '<span style="font-size: 13px; color: var(--text-muted);">' + formatDate(s.date) + '</span>' +
            '</div>' +
        '</div>';
    }).join('');
}

function renderPredictionSummary() {
    let container = document.getElementById('predictionInfo');
    if (!predictionData || cycles.length < 2) {
        container.innerHTML = '<p style="color: var(--text-muted); text-align: center;">' +
            '<span style="font-size: 24px; display: block; margin-bottom: 10px;">📊</span>' +
            'برای پیش‌بینی دقیق‌تر، حداقل ۲ سیکل کامل ثبت کنید<br>' +
            '<small style="font-size: 12px;">' + cycles.length + ' سیکل ثبت شده</small>' +
        '</p>';
        return;
    }

    container.innerHTML = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">' +
        '<div style="text-align: center; padding: 10px; background: var(--bg-input); border-radius: 10px;">' +
            '<div style="font-size: 13px; color: var(--text-muted);">تخمک‌گذاری</div>' +
            '<div style="font-size: 18px; font-weight: 600; color: #ffc107;">' + formatDate(predictionData.ovulation_date) + '</div>' +
        '</div>' +
        '<div style="text-align: center; padding: 10px; background: var(--bg-input); border-radius: 10px;">' +
            '<div style="font-size: 13px; color: var(--text-muted);">شروع بعدی</div>' +
            '<div style="font-size: 18px; font-weight: 600; color: #f5576c;">' + formatDate(predictionData.next_period_start) + '</div>' +
        '</div>' +
    '</div>';
}

// ============================================
// رندر سیکل‌ها
// ============================================
function renderCycles() {
    let container = document.getElementById('cyclesList');
    let sortedCycles = [...cycles].sort((a, b) => new Date(b.start_date) - new Date(a.start_date));

    if (sortedCycles.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 40px;">هیچ سیکلی ثبت نشده است</p>';
        return;
    }

    container.innerHTML = sortedCycles.map(cycle => {
        let flowMap = { 'light': 'کم', 'medium': 'متوسط', 'heavy': 'زیاد' };
        let endDate = cycle.end_date ? formatDate(cycle.end_date) : 'در جریان';
        return '<div class="cycle-item">' +
            '<div class="cycle-info">' +
                '<span class="cycle-date">📅 ' + formatDate(cycle.start_date) + ' - ' + endDate + '</span>' +
                '<span class="cycle-flow">🩸 ' + (flowMap[cycle.flow] || cycle.flow) + '</span>' +
                (cycle.notes ? '<span style="font-size: 13px; color: var(--text-muted);">📝 ' + escapeHtml(cycle.notes) + '</span>' : '') +
            '</div>' +
            '<div class="cycle-actions">' +
                '<button class="delete-btn" onclick="deleteCycle(\'' + cycle.id + '\')"><span class="icon icon-trash"></span></button>' +
            '</div>' +
        '</div>';
    }).join('');
}

// ============================================
// رندر علائم
// ============================================
function renderSymptoms() {
    let container = document.getElementById('symptomsList');
    let filterType = document.getElementById('symptomFilterType').value;
    let filterDate = document.getElementById('symptomFilterDate').value;

    let filtered = [...symptoms];
    if (filterType) filtered = filtered.filter(s => s.type === filterType);
    if (filterDate) filtered = filtered.filter(s => s.date === filterDate);

    filtered.sort((a, b) => new Date(b.date) - new Date(a.date));

    if (filtered.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 40px;">هیچ علامتی یافت نشد</p>';
        return;
    }

    let typeMap = {
        'pain': 'درد',
        'mood': 'خلق و خو',
        'physical': 'جسمی',
        'digestive': 'گوارشی',
        'other': 'سایر'
    };

    container.innerHTML = filtered.map(s => {
        return '<div class="symptom-item">' +
            '<div class="symptom-info">' +
                '<span class="symptom-type">' + (typeMap[s.type] || s.type) + '</span>' +
                '<span class="symptom-severity ' + s.severity + '">' + (s.severity === 'low' ? 'کم' : s.severity === 'medium' ? 'متوسط' : 'زیاد') + '</span>' +
                '<span style="font-size: 13px; color: var(--text-muted);">' + formatDate(s.date) + '</span>' +
                (s.notes ? '<span style="font-size: 13px; color: var(--text-muted);">📝 ' + escapeHtml(s.notes) + '</span>' : '') +
            '</div>' +
            '<div class="symptom-actions">' +
                '<button class="delete-btn" onclick="deleteSymptom(\'' + s.id + '\')"><span class="icon icon-trash"></span></button>' +
            '</div>' +
        '</div>';
    }).join('');
}

// ============================================
// رندر پیش‌بینی
// ============================================
function renderPrediction() {
    if (!predictionData || cycles.length < 2) {
        document.getElementById('predAvgCycle').textContent = '-';
        document.getElementById('predNextStart').textContent = '-';
        document.getElementById('predOvulation').textContent = '-';
        document.getElementById('predFertile').textContent = '-';
        return;
    }

    document.getElementById('predAvgCycle').textContent = toPersianNumbers(predictionData.avg_cycle_length) + ' روز';
    document.getElementById('predNextStart').textContent = formatDate(predictionData.next_period_start);
    document.getElementById('predOvulation').textContent = formatDate(predictionData.ovulation_date);
    document.getElementById('predFertile').textContent = formatDate(predictionData.fertile_window_start) + ' - ' + formatDate(predictionData.fertile_window_end);
}

// ============================================
// مدیریت سیکل‌ها
// ============================================
document.getElementById('addCycleBtn').addEventListener('click', function() {
    editCycleId = null;
    document.getElementById('editCycleId').value = '';
    document.getElementById('cycleStartDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('cycleEndDate').value = '';
    document.getElementById('cycleFlow').value = 'medium';
    document.getElementById('cycleNotes').value = '';
    document.getElementById('cycleModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
});

function closeCycleModal() {
    document.getElementById('cycleModal').style.display = 'none';
    document.body.style.overflow = '';
    editCycleId = null;
}

document.getElementById('saveCycleBtn').addEventListener('click', function() {
    let startDate = document.getElementById('cycleStartDate').value;
    if (!startDate) {
        showToast('لطفاً تاریخ شروع را وارد کنید', 'error');
        return;
    }

    let data = {
        start_date: startDate,
        end_date: document.getElementById('cycleEndDate').value || '',
        flow: document.getElementById('cycleFlow').value,
        notes: document.getElementById('cycleNotes').value
    };

    if (editCycleId) {
        // ویرایش سیکل
        let cycle = cycles.find(c => c.id === editCycleId);
        if (cycle) {
            Object.assign(cycle, data);
            saveCycles();
        }
    } else {
        // افزودن سیکل جدید
        let formData = new FormData();
        formData.append('action', 'add_cycle');
        formData.append('start_date', data.start_date);
        formData.append('end_date', data.end_date);
        formData.append('flow', data.flow);
        formData.append('notes', data.notes);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    cycles = result.cycles;
                    predictionData = result.prediction;
                    currentPhase = result.phase;
                    closeCycleModal();
                    showToast('سیکل با موفقیت ثبت شد', 'success');
                    renderAll();
                }
            });
    }
});

function deleteCycle(id) {
    if (!confirm('آیا از حذف این سیکل مطمئن هستید؟')) return;

    let formData = new FormData();
    formData.append('action', 'delete_cycle');
    formData.append('id', id);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                cycles = result.cycles;
                predictionData = result.prediction;
                currentPhase = result.phase;
                showToast('سیکل حذف شد', 'success');
                renderAll();
            }
        });
}

function saveCycles() {
    // برای ویرایش سیکل
    let formData = new FormData();
    formData.append('action', 'save_cycles');
    formData.append('cycles', JSON.stringify(cycles));
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                cycles = result.cycles;
                predictionData = result.prediction;
                currentPhase = result.phase;
                closeCycleModal();
                showToast('سیکل با موفقیت ویرایش شد', 'success');
                renderAll();
            }
        });
}

// ============================================
// مدیریت علائم
// ============================================
document.getElementById('addSymptomBtn').addEventListener('click', function() {
    editSymptomId = null;
    document.getElementById('editSymptomId').value = '';
    document.getElementById('symptomDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('symptomType').value = 'pain';
    document.getElementById('symptomSeverity').value = 'medium';
    document.getElementById('symptomNotes').value = '';
    document.getElementById('symptomModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
});

function closeSymptomModal() {
    document.getElementById('symptomModal').style.display = 'none';
    document.body.style.overflow = '';
    editSymptomId = null;
}

document.getElementById('saveSymptomBtn').addEventListener('click', function() {
    let date = document.getElementById('symptomDate').value;
    if (!date) {
        showToast('لطفاً تاریخ را وارد کنید', 'error');
        return;
    }

    let data = {
        date: date,
        type: document.getElementById('symptomType').value,
        severity: document.getElementById('symptomSeverity').value,
        notes: document.getElementById('symptomNotes').value
    };

    let formData = new FormData();
    formData.append('action', 'add_symptom');
    formData.append('date', data.date);
    formData.append('type', data.type);
    formData.append('severity', data.severity);
    formData.append('notes', data.notes);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                symptoms = result.symptoms;
                closeSymptomModal();
                showToast('علامت با موفقیت ثبت شد', 'success');
                renderAll();
            }
        });
});

function deleteSymptom(id) {
    if (!confirm('آیا از حذف این علامت مطمئن هستید؟')) return;

    let formData = new FormData();
    formData.append('action', 'delete_symptom');
    formData.append('id', id);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                symptoms = result.symptoms;
                showToast('علامت حذف شد', 'success');
                renderAll();
            }
        });
}

// ============================================
// فیلتر علائم
// ============================================
document.getElementById('symptomFilterType').addEventListener('change', renderSymptoms);
document.getElementById('symptomFilterDate').addEventListener('change', renderSymptoms);

// ============================================
// رندر همه
// ============================================
function renderAll() {
    renderCurrentStatus();
    if (currentTab === 'dashboard') renderDashboard();
    if (currentTab === 'cycles') renderCycles();
    if (currentTab === 'symptoms') renderSymptoms();
    if (currentTab === 'predict') renderPrediction();
}

// ============================================
// بستن مودال‌ها با کلیک بیرون
// ============================================
window.onclick = function(event) {
    if (event.target === document.getElementById('cycleModal')) closeCycleModal();
    if (event.target === document.getElementById('symptomModal')) closeSymptomModal();
};

// ============================================
// شروع
// ============================================
renderAll();
