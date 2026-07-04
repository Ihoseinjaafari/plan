// health/script.js

// ============================================
// متغیرها
// ============================================
let currentTab = 'calendar';
let editCycleId = null;
let editSymptomId = null;

// ============================================
// توابع تبدیل تاریخ شمسی
// ============================================
function gregorianToJalali(gy, gm, gd) {
    var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    var gy2 = (gm > 2) ? (gy + 1) : gy;
    var days = 355666 + (365 * gy) + parseInt((gy2 + 3) / 4) - parseInt((gy2 + 99) / 100) + parseInt((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
    var jy = -1595 + (33 * parseInt(days / 12053));
    days %= 12053;
    jy += 4 * parseInt(days / 1461);
    days %= 1461;
    if (days > 365) {
        jy += parseInt((days - 1) / 365);
        days = (days - 1) % 365;
    }
    var jm, jd;
    if (days < 186) {
        jm = 1 + parseInt(days / 31);
        jd = 1 + (days % 31);
    } else {
        jm = 7 + parseInt((days - 186) / 30);
        jd = 1 + ((days - 186) % 30);
    }
    return [jy, jm, jd];
}

function jalaliToGregorian(jy, jm, jd) {
    jy += 1595;
    var days = -355668 + (365 * jy) + (parseInt(jy / 33) * 8) + parseInt(((jy % 33) + 3) / 4) + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    var gy = 400 * parseInt(days / 146097);
    days %= 146097;
    if (days > 36524) {
        gy += 100 * parseInt(--days / 36524);
        days %= 36524;
        if (days >= 365) days++;
    }
    gy += 4 * parseInt(days / 1461);
    days %= 1461;
    if (days > 365) {
        gy += parseInt((days - 1) / 365);
        days = (days - 1) % 365;
    }
    var gd = days + 1;
    var sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    var gm = 0;
    for (gm = 1; gm < 13 && gd > sal_a[gm]; gm++) {
        gd -= sal_a[gm];
    }
    return [gy, gm, gd];
}

function toGregorianDate(jalaliStr) {
    if (!jalaliStr) return '';
    var parts = jalaliStr.split('/');
    if (parts.length !== 3) return '';
    var jy = parseInt(parts[0]);
    var jm = parseInt(parts[1]);
    var jd = parseInt(parts[2]);
    var gregorian = jalaliToGregorian(jy, jm, jd);
    return gregorian[0] + '-' + String(gregorian[1]).padStart(2, '0') + '-' + String(gregorian[2]).padStart(2, '0');
}

function toJalaliDate(dateStr) {
    if (!dateStr) return '';
    var parts = dateStr.split('-');
    if (parts.length !== 3) return '';
    var gy = parseInt(parts[0]);
    var gm = parseInt(parts[1]);
    var gd = parseInt(parts[2]);
    var jalali = gregorianToJalali(gy, gm, gd);
    return jalali[0] + '/' + jalali[1] + '/' + jalali[2];
}

// ============================================
// کلاس Picker تاریخ شمسی
// ============================================
class JalaliDatePicker {
    constructor(inputId, calendarId, options = {}) {
        this.input = document.getElementById(inputId);
        this.calendar = document.getElementById(calendarId);
        this.onSelect = options.onSelect || null;
        this.currentDate = options.defaultDate || null;
        
        if (!this.input || !this.calendar) return;
        
        if (this.currentDate) {
            this.input.value = this.currentDate;
        } else if (this.input.value) {
            this.currentDate = this.input.value;
        } else {
            this.currentDate = todayJalali || '1400/01/01';
            this.input.value = this.currentDate;
        }
        
        var parts = this.currentDate.split('/');
        if (parts.length === 3) {
            this.currentYear = parseInt(parts[0]);
            this.currentMonth = parseInt(parts[1]);
            this.currentDay = parseInt(parts[2]);
        } else {
            this.currentYear = 1400;
            this.currentMonth = 1;
            this.currentDay = 1;
        }
        
        this.initEvents();
        this.render();
    }
    
    initEvents() {
        var self = this;
        this.input.addEventListener('click', function(e) {
            e.stopPropagation();
            self.toggle();
        });
        
        this.calendar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        document.addEventListener('click', function(e) {
            if (!self.calendar.contains(e.target) && e.target !== self.input) {
                self.calendar.classList.remove('show');
            }
        });
    }
    
    toggle() {
        this.calendar.classList.toggle('show');
        if (this.calendar.classList.contains('show')) {
            this.render();
        }
    }
    
    goToMonth(year, month) {
        event.stopPropagation();
        this.currentYear = year;
        this.currentMonth = month;
        this.render();
    }
    
    selectDay(day) {
        this.currentDay = day;
        var dateStr = this.currentYear + '/' + this.currentMonth + '/' + day;
        this.currentDate = dateStr;
        this.input.value = dateStr;
        this.calendar.classList.remove('show');
        if (this.onSelect) {
            this.onSelect(dateStr);
        }
    }
    
    render() {
        var self = this;
        var monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        var dayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
        
        var firstDayGreg = jalaliToGregorian(this.currentYear, this.currentMonth, 1);
        var firstDayWeekday = new Date(firstDayGreg[0], firstDayGreg[1] - 1, firstDayGreg[2]).getDay();
        var startOffset = (firstDayWeekday + 1) % 7;
        
        var daysInMonth = (this.currentMonth <= 6) ? 31 : (this.currentMonth < 12 ? 30 : ((this.currentYear % 4 === 3) ? 30 : 29));
        
        var today = new Date();
        var todayJalali = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
        var todayStr = todayJalali[0] + '/' + todayJalali[1] + '/' + todayJalali[2];
        
        var html = '';
        html += '<div class="calendar-header">';
        html += '<button class="nav-btn" onclick="event.stopPropagation(); window.datePickers[\'' + this.calendar.id + '\'].goToMonth(' + this.currentYear + ', ' + (this.currentMonth - 1) + ')">‹</button>';
        html += '<span class="month-year">' + monthNames[this.currentMonth - 1] + ' ' + this.currentYear + '</span>';
        html += '<button class="nav-btn" onclick="event.stopPropagation(); window.datePickers[\'' + this.calendar.id + '\'].goToMonth(' + this.currentYear + ', ' + (this.currentMonth + 1) + ')">›</button>';
        html += '</div>';
        
        html += '<div class="calendar-weekdays">';
        for (var i = 0; i < 7; i++) {
            html += '<div>' + dayNames[i] + '</div>';
        }
        html += '</div>';
        
        html += '<div class="calendar-days">';
        for (var i = 0; i < startOffset; i++) {
            html += '<div class="calendar-day empty"></div>';
        }
        for (var d = 1; d <= daysInMonth; d++) {
            var dateStr = this.currentYear + '/' + this.currentMonth + '/' + d;
            var isToday = (dateStr === todayStr);
            var isSelected = (dateStr === this.currentDate);
            var cls = 'calendar-day';
            if (isToday) cls += ' today';
            if (isSelected) cls += ' selected';
            html += '<div class="' + cls + '" onclick="event.stopPropagation(); window.datePickers[\'' + this.calendar.id + '\'].selectDay(' + d + ')">' + d + '</div>';
        }
        html += '</div>';
        
        this.calendar.innerHTML = html;
    }
}

// ============================================
// توابع کمکی
// ============================================
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.className = 'toast ' + type;
    setTimeout(() => t.classList.add('show'), 50);
    setTimeout(() => t.classList.remove('show'), 3000);
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
    return d.toLocaleDateString('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' });
}

function toPersian(str) {
    if (str === undefined || str === null) return '';
    const digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return String(str).replace(/[0-9]/g, d => digits[parseInt(d)]);
}

// ============================================
// مدیریت تب‌ها
// ============================================
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentTab = this.dataset.tab;
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('panel-' + currentTab).classList.add('active');
        
        if (currentTab === 'cycles') renderCycles();
        if (currentTab === 'symptoms') renderSymptoms();
        if (currentTab === 'predict') renderPrediction();
    });
});

// ============================================
// رندر وضعیت فعلی
// ============================================
function renderStatus() {
    const phaseMap = {
        'menstruation': { icon: '🩸', text: 'قاعدگی', color: '#f5576c' },
        'follicular': { icon: '🌱', text: 'فولیکولی', color: '#667eea' },
        'ovulation': { icon: '🥚', text: 'تخمک‌گذاری', color: '#ffc107' },
        'luteal': { icon: '🌙', text: 'لوتئال', color: '#764ba2' },
        'pre_menstrual': { icon: '📌', text: 'پیش‌قاعدگی', color: '#ff6b6b' },
        'not_started': { icon: '📋', text: 'ثبت نشده', color: '#6c757d' }
    };
    const p = phaseMap[currentPhase] || phaseMap['not_started'];
    
    const phaseIcon = document.getElementById('phaseIcon');
    const phaseText = document.getElementById('phaseText');
    if (phaseIcon) phaseIcon.textContent = p.icon;
    if (phaseText) {
        phaseText.textContent = p.text;
        phaseText.style.color = p.color;
    }

    const sorted = [...cycles].sort((a, b) => new Date(b.start_date) - new Date(a.start_date));
    const dayEl = document.getElementById('cycleDay');
    if (dayEl) {
        if (sorted.length > 0) {
            const last = new Date(sorted[0].start_date);
            const today = new Date();
            const days = Math.floor((today - last) / (1000 * 60 * 60 * 24)) + 1;
            dayEl.textContent = toPersian(days);
        } else {
            dayEl.textContent = '-';
        }
    }

    const rem = document.getElementById('daysRemaining');
    const next = document.getElementById('nextPeriod');
    if (predictionData) {
        if (rem) rem.textContent = toPersian(predictionData.days_until_next) + ' روز';
        if (next) next.textContent = formatDate(predictionData.next_period_start);
    } else {
        if (rem) rem.textContent = '-';
        if (next) next.textContent = '-';
    }

    const statCycles = document.getElementById('statCycles');
    const statSymptoms = document.getElementById('statSymptoms');
    const statAvgCycle = document.getElementById('statAvgCycle');
    if (statCycles) statCycles.textContent = toPersian(cycles.length);
    if (statSymptoms) statSymptoms.textContent = toPersian(symptoms.length);
    if (statAvgCycle) statAvgCycle.textContent = predictionData ? toPersian(predictionData.avg_cycle_length) + ' روز' : '-';
}

// ============================================
// انتخاب تاریخ (نمایش علائم و رویدادهای آن روز)
// ============================================
function selectDate(dateStr) {
    selectedDate = dateStr;
    // تبدیل تاریخ میلادی به شمسی برای نمایش
    const parts = dateStr.split('-');
    const gy = parseInt(parts[0]);
    const gm = parseInt(parts[1]);
    const gd = parseInt(parts[2]);
    const jalali = gregorianToJalali(gy, gm, gd);
    const display = jalali[0] + '/' + jalali[1] + '/' + jalali[2];
    const displayEl = document.getElementById('selectedDateDisplay');
    if (displayEl) displayEl.textContent = '📅 ' + display;
    
    // پیدا کردن سیکل در این تاریخ
    const hasCycle = cycles.some(c => {
        const start = new Date(c.start_date);
        const end = c.end_date ? new Date(c.end_date) : new Date(start.getTime() + 5 * 24 * 60 * 60 * 1000);
        const current = new Date(dateStr);
        return current >= start && current <= end;
    });
    
    // پیدا کردن علائم در این تاریخ
    const daySymptoms = symptoms.filter(s => s.date === dateStr);
    
    // پیدا کردن سیکل پیش‌بینی شده در این تاریخ
    const hasPredicted = futureCycles.some(f => {
        const start = new Date(f.start_date);
        const end = new Date(f.end_date);
        const current = new Date(dateStr);
        return current >= start && current <= end;
    });
    
    let html = '';
    
    // نمایش سیکل ثبت‌شده
    if (hasCycle) {
        const cycle = cycles.find(c => {
            const start = new Date(c.start_date);
            const end = c.end_date ? new Date(c.end_date) : new Date(start.getTime() + 5 * 24 * 60 * 60 * 1000);
            const current = new Date(dateStr);
            return current >= start && current <= end;
        });
        const flowMap = { 'light': '🟢 کم', 'medium': '🟡 متوسط', 'heavy': '🔴 زیاد' };
        html += '<div class="day-item cycle-item">';
        html += '🩸 <strong>سیکل قاعدگی</strong> - ' + (flowMap[cycle.flow] || cycle.flow);
        if (cycle.notes) html += ' 📝 ' + escapeHtml(cycle.notes);
        html += '</div>';
    }
    
    // نمایش سیکل پیش‌بینی شده
    if (hasPredicted && !hasCycle) {
        const future = futureCycles.find(f => {
            const start = new Date(f.start_date);
            const end = new Date(f.end_date);
            const current = new Date(dateStr);
            return current >= start && current <= end;
        });
        html += '<div class="day-item predicted-item">';
        html += '🔮 <strong>پیش‌بینی سیکل</strong> (تخمینی)';
        if (future && future.cycle_number) html += ' - سیکل ' + future.cycle_number;
        html += '</div>';
    }
    
    // نمایش علائم
    if (daySymptoms.length > 0) {
        const typeMap = { 'pain': 'درد', 'mood': 'خلق', 'physical': 'جسمی', 'digestive': 'گوارشی', 'other': 'سایر' };
        const severityMap = { 'low': 'کم', 'medium': 'متوسط', 'high': 'زیاد' };
        daySymptoms.forEach(s => {
            html += '<div class="day-item symptom-item">';
            html += '💊 <strong>' + (typeMap[s.type] || s.type) + '</strong> - ' + severityMap[s.severity];
            if (s.notes) html += ' 📝 ' + escapeHtml(s.notes);
            html += '</div>';
        });
    }
    
    const dayDetails = document.getElementById('dayDetails');
    if (dayDetails) {
        if (!hasCycle && !hasPredicted && daySymptoms.length === 0) {
            dayDetails.innerHTML = '<p class="empty">هیچ رویدادی برای این روز ثبت نشده است</p>';
        } else {
            dayDetails.innerHTML = html;
        }
    }
}

// ============================================
// رندر سیکل‌ها
// ============================================
function renderCycles() {
    const container = document.getElementById('cyclesList');
    if (!container) return;
    
    const sorted = [...cycles].sort((a, b) => new Date(b.start_date) - new Date(a.start_date));
    
    if (sorted.length === 0) {
        container.innerHTML = '<p class="empty">هیچ سیکلی ثبت نشده است</p>';
        return;
    }
    
    const flowMap = { 'light': '🟢 کم', 'medium': '🟡 متوسط', 'heavy': '🔴 زیاد' };
    container.innerHTML = sorted.map(c => {
        const endDate = c.end_date ? formatDate(c.end_date) : 'در جریان';
        return '<div class="item-row">' +
            '<div class="info">' +
                '<span class="main">📅 ' + formatDate(c.start_date) + ' - ' + endDate + '</span>' +
                '<span class="badge flow-' + c.flow + '">' + flowMap[c.flow] + '</span>' +
                (c.notes ? '<span class="note">📝 ' + escapeHtml(c.notes) + '</span>' : '') +
            '</div>' +
            '<div class="actions">' +
                '<button class="edit-btn" onclick="openEditCycle(\'' + c.id + '\')">✏️</button>' +
                '<button class="delete-btn" onclick="deleteCycle(\'' + c.id + '\')">🗑️</button>' +
            '</div>' +
        '</div>';
    }).join('');
}

// ============================================
// رندر علائم
// ============================================
function renderSymptoms() {
    const container = document.getElementById('symptomsList');
    if (!container) return;
    
    const sorted = [...symptoms].sort((a, b) => new Date(b.date) - new Date(a.date));
    
    if (sorted.length === 0) {
        container.innerHTML = '<p class="empty">هیچ علامتی ثبت نشده است</p>';
        return;
    }
    
    const typeMap = { 'pain': 'درد', 'mood': 'خلق', 'physical': 'جسمی', 'digestive': 'گوارشی', 'other': 'سایر' };
    const severityMap = { 'low': 'کم', 'medium': 'متوسط', 'high': 'زیاد' };
    
    container.innerHTML = sorted.map(s => {
        return '<div class="item-row">' +
            '<div class="info">' +
                '<span class="main">💊 ' + typeMap[s.type] + '</span>' +
                '<span class="badge sev-' + s.severity + '">' + severityMap[s.severity] + '</span>' +
                '<span class="meta">' + formatDate(s.date) + '</span>' +
                (s.notes ? '<span class="note">📝 ' + escapeHtml(s.notes) + '</span>' : '') +
            '</div>' +
            '<div class="actions">' +
                '<button class="edit-btn" onclick="openEditSymptom(\'' + s.id + '\')">✏️</button>' +
                '<button class="delete-btn" onclick="deleteSymptom(\'' + s.id + '\')">🗑️</button>' +
            '</div>' +
        '</div>';
    }).join('');
}

// ============================================
// رندر پیش‌بینی
// ============================================
function renderPrediction() {
    const pAvg = document.getElementById('pAvg');
    const pNext = document.getElementById('pNext');
    const pOv = document.getElementById('pOvulation');
    const pFer = document.getElementById('pFertile');
    
    if (!predictionData || cycles.length < 2) {
        if (pAvg) pAvg.textContent = '-';
        if (pNext) pNext.textContent = '-';
        if (pOv) pOv.textContent = '-';
        if (pFer) pFer.textContent = '-';
        return;
    }
    
    if (pAvg) pAvg.textContent = toPersian(predictionData.avg_cycle_length) + ' روز';
    if (pNext) pNext.textContent = formatDate(predictionData.next_period_start);
    if (pOv) pOv.textContent = formatDate(predictionData.ovulation_date);
    if (pFer) pFer.textContent = formatDate(predictionData.fertile_window_start) + ' - ' + formatDate(predictionData.fertile_window_end);
}

// ============================================
// مدیریت سیکل‌ها
// ============================================
function openAddCycleModal() {
    editCycleId = null;
    document.getElementById('cycleModalTitle').textContent = '📅 ثبت سیکل جدید';
    document.getElementById('saveCycleBtn').textContent = 'ذخیره';
    document.getElementById('editCycleId').value = '';
    
    const date = selectedDate || currentJalaliDate;
    const parts = date.split('-');
    const jalaliDate = parts[0] + '/' + parts[1] + '/' + parts[2];
    
    document.getElementById('cycleStartDate').value = jalaliDate;
    document.getElementById('cycleEndDate').value = '';
    document.getElementById('cycleFlow').value = 'medium';
    document.getElementById('cycleNotes').value = '';
    
    document.getElementById('cycleModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    updateCyclePickers(jalaliDate);
}

function openEditCycle(id) {
    const cycle = cycles.find(c => c.id === id);
    if (!cycle) return;
    
    editCycleId = id;
    document.getElementById('cycleModalTitle').textContent = '✏️ ویرایش سیکل';
    document.getElementById('saveCycleBtn').textContent = 'ویرایش';
    document.getElementById('editCycleId').value = id;
    
    const jalaliStart = toJalaliDate(cycle.start_date);
    const jalaliEnd = cycle.end_date ? toJalaliDate(cycle.end_date) : '';
    
    document.getElementById('cycleStartDate').value = jalaliStart;
    document.getElementById('cycleEndDate').value = jalaliEnd || '';
    document.getElementById('cycleFlow').value = cycle.flow || 'medium';
    document.getElementById('cycleNotes').value = cycle.notes || '';
    
    document.getElementById('cycleModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    updateCyclePickers(jalaliStart);
}

function updateCyclePickers(jalaliDate) {
    if (datePickers['cycleDateCalendar']) {
        const p = jalaliDate.split('/');
        if (p.length === 3) {
            datePickers['cycleDateCalendar'].currentYear = parseInt(p[0]);
            datePickers['cycleDateCalendar'].currentMonth = parseInt(p[1]);
            datePickers['cycleDateCalendar'].currentDay = parseInt(p[2]);
            datePickers['cycleDateCalendar'].currentDate = jalaliDate;
            datePickers['cycleDateCalendar'].render();
        }
    }
}

function closeCycleModal() {
    document.getElementById('cycleModal').style.display = 'none';
    document.body.style.overflow = '';
}

function initCycleSave() {
    const btn = document.getElementById('saveCycleBtn');
    if (btn) {
        btn.addEventListener('click', function() {
            const startJalali = document.getElementById('cycleStartDate').value;
            if (!startJalali) { showToast('لطفاً تاریخ شروع را وارد کنید', 'error'); return; }
            
            const startDate = toGregorianDate(startJalali);
            const endJalali = document.getElementById('cycleEndDate').value;
            const endDate = endJalali ? toGregorianDate(endJalali) : '';
            
            const isEdit = document.getElementById('editCycleId').value;
            const action = isEdit ? 'edit_cycle' : 'add_cycle';
            
            const data = {
                id: isEdit,
                start_date: startDate,
                end_date: endDate,
                flow: document.getElementById('cycleFlow').value,
                notes: document.getElementById('cycleNotes').value
            };
            
            const fd = new FormData();
            fd.append('action', action);
            Object.keys(data).forEach(k => fd.append(k, data[k]));
            
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        cycles = res.cycles;
                        predictionData = res.prediction;
                        currentPhase = res.phase;
                        futureCycles = res.future_cycles || [];
                        closeCycleModal();
                        showToast(isEdit ? 'سیکل ویرایش شد' : 'سیکل با موفقیت ثبت شد', 'success');
                        renderAll();
                        if (selectedDate) selectDate(selectedDate);
                        if (currentTab === 'cycles') renderCycles();
                    }
                });
        });
    }
}

function deleteCycle(id) {
    if (!confirm('آیا از حذف این سیکل مطمئن هستید؟')) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_cycle');
    fd.append('id', id);
    
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                cycles = res.cycles;
                predictionData = res.prediction;
                currentPhase = res.phase;
                futureCycles = res.future_cycles || [];
                showToast('سیکل حذف شد', 'success');
                renderAll();
                if (selectedDate) selectDate(selectedDate);
                if (currentTab === 'cycles') renderCycles();
            }
        });
}

// ============================================
// مدیریت علائم
// ============================================
function openAddSymptomModal() {
    editSymptomId = null;
    document.getElementById('symptomModalTitle').textContent = '💊 ثبت علامت جدید';
    document.getElementById('saveSymptomBtn').textContent = 'ذخیره';
    document.getElementById('editSymptomId').value = '';
    
    const date = selectedDate || currentJalaliDate;
    const parts = date.split('-');
    const jalaliDate = parts[0] + '/' + parts[1] + '/' + parts[2];
    
    document.getElementById('symptomDate').value = jalaliDate;
    document.getElementById('symptomType').value = 'pain';
    document.getElementById('symptomSeverity').value = 'medium';
    document.getElementById('symptomNotes').value = '';
    
    document.getElementById('symptomModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    updateSymptomPickers(jalaliDate);
}

function openEditSymptom(id) {
    const symptom = symptoms.find(s => s.id === id);
    if (!symptom) return;
    
    editSymptomId = id;
    document.getElementById('symptomModalTitle').textContent = '✏️ ویرایش علامت';
    document.getElementById('saveSymptomBtn').textContent = 'ویرایش';
    document.getElementById('editSymptomId').value = id;
    
    const jalaliDate = toJalaliDate(symptom.date);
    
    document.getElementById('symptomDate').value = jalaliDate;
    document.getElementById('symptomType').value = symptom.type || 'pain';
    document.getElementById('symptomSeverity').value = symptom.severity || 'medium';
    document.getElementById('symptomNotes').value = symptom.notes || '';
    
    document.getElementById('symptomModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    updateSymptomPickers(jalaliDate);
}

function updateSymptomPickers(jalaliDate) {
    if (datePickers['symptomDateCalendar']) {
        const p = jalaliDate.split('/');
        if (p.length === 3) {
            datePickers['symptomDateCalendar'].currentYear = parseInt(p[0]);
            datePickers['symptomDateCalendar'].currentMonth = parseInt(p[1]);
            datePickers['symptomDateCalendar'].currentDay = parseInt(p[2]);
            datePickers['symptomDateCalendar'].currentDate = jalaliDate;
            datePickers['symptomDateCalendar'].render();
        }
    }
}

function closeSymptomModal() {
    document.getElementById('symptomModal').style.display = 'none';
    document.body.style.overflow = '';
}

function initSymptomSave() {
    const btn = document.getElementById('saveSymptomBtn');
    if (btn) {
        btn.addEventListener('click', function() {
            const jalaliDate = document.getElementById('symptomDate').value;
            if (!jalaliDate) { showToast('لطفاً تاریخ را وارد کنید', 'error'); return; }
            
            const date = toGregorianDate(jalaliDate);
            const isEdit = document.getElementById('editSymptomId').value;
            const action = isEdit ? 'edit_symptom' : 'add_symptom';
            
            const data = {
                id: isEdit,
                date: date,
                type: document.getElementById('symptomType').value,
                severity: document.getElementById('symptomSeverity').value,
                notes: document.getElementById('symptomNotes').value
            };
            
            const fd = new FormData();
            fd.append('action', action);
            Object.keys(data).forEach(k => fd.append(k, data[k]));
            
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        symptoms = res.symptoms;
                        closeSymptomModal();
                        showToast(isEdit ? 'علامت ویرایش شد' : 'علامت با موفقیت ثبت شد', 'success');
                        renderAll();
                        if (selectedDate) selectDate(selectedDate);
                        if (currentTab === 'symptoms') renderSymptoms();
                    }
                });
        });
    }
}

function deleteSymptom(id) {
    if (!confirm('آیا از حذف این علامت مطمئن هستید؟')) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_symptom');
    fd.append('id', id);
    
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                symptoms = res.symptoms;
                showToast('علامت حذف شد', 'success');
                renderAll();
                if (selectedDate) selectDate(selectedDate);
                if (currentTab === 'symptoms') renderSymptoms();
            }
        });
}

// ============================================
// مقداردهی Pickerها
// ============================================
function initDatePickers() {
    const pickers = [
        { id: 'cycleStartDate', cal: 'cycleDateCalendar' },
        { id: 'cycleEndDate', cal: 'cycleEndCalendar' },
        { id: 'symptomDate', cal: 'symptomDateCalendar' }
    ];
    
    pickers.forEach(p => {
        const inputEl = document.getElementById(p.id);
        const calEl = document.getElementById(p.cal);
        if (inputEl && calEl) {
            const picker = new JalaliDatePicker(p.id, p.cal, {
                defaultDate: todayJalali || '1400/01/01',
                onSelect: function(dateStr) {
                    document.getElementById(p.id).value = dateStr;
                }
            });
            datePickers[p.cal] = picker;
        }
    });
    
    window.datePickers = datePickers;
}

// ============================================
// رندر همه
// ============================================
function renderAll() {
    renderStatus();
    if (currentTab === 'cycles') renderCycles();
    if (currentTab === 'symptoms') renderSymptoms();
    if (currentTab === 'predict') renderPrediction();
}

// ============================================
// بستن مودال با کلیک بیرون
// ============================================
window.onclick = function(e) {
    const cycleModal = document.getElementById('cycleModal');
    const symptomModal = document.getElementById('symptomModal');
    if (e.target === cycleModal) closeCycleModal();
    if (e.target === symptomModal) closeSymptomModal();
};

// ============================================
// شروع
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initDatePickers();
    initCycleSave();
    initSymptomSave();
    renderAll();
    
    const urlParams = new URLSearchParams(window.location.search);
    const dateParam = urlParams.get('date');
    if (dateParam) {
        selectDate(dateParam);
    }
    
    console.log('✅ سلامت زنان با پیش‌بینی سیکل‌ها و نمایش علائم بارگذاری شد.');
});