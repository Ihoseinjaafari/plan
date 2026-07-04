// health/script.js

// ============================================
// متغیرها
// ============================================
let cycles = [];
let symptoms = [];
let predictionData = null;
let currentPhase = 'not_started';
let currentTab = 'dashboard';
let editCycleId = null;
let editSymptomId = null;

// ============================================
// تابع مقداردهی اولیه - فراخوانی از index.php
// ============================================
function initHealthApp() {
    // مقداردهی متغیرها از داده‌های PHP
    cycles = (typeof initialCycles !== 'undefined') ? initialCycles : [];
    symptoms = (typeof initialSymptoms !== 'undefined') ? initialSymptoms : [];
    predictionData = (typeof prediction !== 'undefined') ? prediction : null;
    currentPhase = (typeof currentPhase !== 'undefined') ? currentPhase : 'not_started';
    
    // رندر اولیه
    renderAll();
    
    console.log('Health App initialized with', cycles.length, 'cycles and', symptoms.length, 'symptoms');
}

// ============================================
// توابع کمکی - شامل تبدیل تاریخ میلادی به شمسی
// ============================================

// توابع تبدیل تاریخ میلادی به شمسی (از تقویم پروژه)
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

function toJalaliDate(dateStr) {
    if (!dateStr) return '';
    var parts = dateStr.split('-');
    var gy = parseInt(parts[0]);
    var gm = parseInt(parts[1]);
    var gd = parseInt(parts[2]);
    var jalali = gregorianToJalali(gy, gm, gd);
    return jalali[0] + '/' + jalali[1] + '/' + jalali[2];
}

function toGregorianDate(jalaliStr) {
    if (!jalaliStr) return '';
    var parts = jalaliStr.split('/');
    var jy = parseInt(parts[0]);
    var jm = parseInt(parts[1]);
    var jd = parseInt(parts[2]);
    var gregorian = jalaliToGregorian(jy, jm, jd);
    return gregorian[0] + '-' + String(gregorian[1]).padStart(2, '0') + '-' + String(gregorian[2]).padStart(2, '0');
}

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
    // تبدیل تاریخ میلادی به شمسی و نمایش به فارسی
    return toJalaliDate(dateStr);
}

function toPersianNumbers(str) {
    if (str === undefined || str === null) return '';
    let persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return String(str).replace(/[0-9]/g, function(d) { return persianDigits[parseInt(d)]; });
}

// ============================================
// مدیریت تب‌ها
// ============================================
function initTabs() {
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
}

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
    
    const phaseIconEl = document.getElementById('phaseIcon');
    const phaseTextEl = document.getElementById('phaseText');
    if (phaseIconEl) phaseIconEl.textContent = phase.icon;
    if (phaseTextEl) {
        phaseTextEl.textContent = phase.text;
        phaseTextEl.style.color = phase.color;
    }

    let cyclesList = [...cycles].sort((a, b) => new Date(b.start_date) - new Date(a.start_date));

    const cycleDayEl = document.getElementById('cycleDay');
    if (cyclesList.length > 0) {
        let lastCycle = cyclesList[0];
        let lastStart = new Date(lastCycle.start_date);
        let today = new Date();
        let daysSinceStart = Math.floor((today - lastStart) / (1000 * 60 * 60 * 24)) + 1;
        if (cycleDayEl) cycleDayEl.textContent = toPersianNumbers(daysSinceStart);
    } else {
        if (cycleDayEl) cycleDayEl.textContent = '-';
    }

    const daysRemainingEl = document.getElementById('daysRemaining');
    const nextPeriodEl = document.getElementById('nextPeriod');
    if (predictionData) {
        if (daysRemainingEl) daysRemainingEl.textContent = toPersianNumbers(predictionData.days_until_next) + ' روز';
        if (nextPeriodEl) nextPeriodEl.textContent = formatDate(predictionData.next_period_start);
    } else {
        if (daysRemainingEl) daysRemainingEl.textContent = '-';
        if (nextPeriodEl) nextPeriodEl.textContent = '-';
    }

    // آمار
    const statCyclesEl = document.getElementById('statCycles');
    const statSymptomsEl = document.getElementById('statSymptoms');
    const statAvgCycleEl = document.getElementById('statAvgCycle');
    if (statCyclesEl) statCyclesEl.textContent = toPersianNumbers(cycles.length);
    if (statSymptomsEl) statSymptomsEl.textContent = toPersianNumbers(symptoms.length);
    if (statAvgCycleEl) statAvgCycleEl.textContent = predictionData ? toPersianNumbers(predictionData.avg_cycle_length) + ' روز' : '-';
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
    if (!container) return;
    
    // استفاده از تاریخ شمسی برای نمایش تقویم
    let today = new Date();
    let gy = today.getFullYear();
    let gm = today.getMonth() + 1;
    let gd = today.getDate();
    
    // تبدیل به شمسی
    let jalali = gregorianToJalali(gy, gm, gd);
    let jy = jalali[0];
    let jm = jalali[1];
    
    // محاسبه روزهای ماه شمسی جاری
    let daysInMonth = (jm <= 6) ? 31 : (jm <= 11) ? 30 : 31;
    
    // تبدیل اول ماه شمسی به میلادی برای پیدا کردن روز شروع
    let gFirst = jalaliToGregorian(jy, jm, 1);
    let firstDayDate = new Date(gFirst[0], gFirst[1] - 1, gFirst[2]);
    let startDay = (firstDayDate.getDay() + 1) % 7; // تنظیم برای شروع از شنبه
    
    let periodDays = [];
    let fertileDays = [];
    let ovulationDay = null;
    
    // محاسبه روزهای قاعدگی، باروری و تخمک‌گذاری بر اساس تاریخ شمسی
    cycles.forEach(cycle => {
        let startDate = cycle.start_date;
        let endDate = cycle.end_date || '';
        
        // تبدیل تاریخ شروع به شمسی
        let sParts = startDate.split('-');
        let sJalali = gregorianToJalali(parseInt(sParts[0]), parseInt(sParts[1]), parseInt(sParts[2]));
        
        // اگر پایان ندارد، 5 روز اضافه کن
        let jEndDay = sJalali[2] + 4;
        if (endDate) {
            let eParts = endDate.split('-');
            let eJalali = gregorianToJalali(parseInt(eParts[0]), parseInt(eParts[1]), parseInt(eParts[2]));
            jEndDay = eJalali[2];
        }
        
        // اگر این سیکل در ماه جاری است
        if (sJalali[0] === jy && sJalali[1] === jm) {
            for (let d = sJalali[2]; d <= jEndDay; d++) {
                periodDays.push(d);
            }
        }
    });
    
    if (predictionData && predictionData.fertile_window_start && predictionData.fertile_window_end) {
        let fsParts = predictionData.fertile_window_start.split('-');
        let feParts = predictionData.fertile_window_end.split('-');
        let fsJalali = gregorianToJalali(parseInt(fsParts[0]), parseInt(fsParts[1]), parseInt(fsParts[2]));
        let feJalali = gregorianToJalali(parseInt(feParts[0]), parseInt(feParts[1]), parseInt(feParts[2]));
        
        if (fsJalali[0] === jy && fsJalali[1] === jm) {
            for (let d = fsJalali[2]; d <= feJalali[2]; d++) {
                fertileDays.push(d);
            }
        }
    }
    
    if (predictionData && predictionData.ovulation_date) {
        let ovParts = predictionData.ovulation_date.split('-');
        let ovJalali = gregorianToJalali(parseInt(ovParts[0]), parseInt(ovParts[1]), parseInt(ovParts[2]));
        if (ovJalali[0] === jy && ovJalali[1] === jm) {
            ovulationDay = ovJalali[2];
        }
    }
    
    // نام روزهای هفته به فارسی (شنبه تا جمعه)
    let dayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
    let html = '<div class="mini-calendar">';
    dayNames.forEach(name => {
        html += '<div class="cal-header">' + name + '</div>';
    });
    
    // روزهای خالی قبل از شروع ماه
    for (let i = 0; i < startDay; i++) {
        html += '<div class="cal-day"></div>';
    }
    
    // روزهای ماه
    for (let d = 1; d <= daysInMonth; d++) {
        let cls = 'cal-day';
        if (d === gd && jm === jalali[1] && jy === jalali[0]) cls += ' today';
        if (periodDays.includes(d)) cls += ' period';
        if (fertileDays.includes(d)) cls += ' fertile';
        if (ovulationDay && d === ovulationDay) cls += ' ovulation';
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
    if (!container) return;
    
    let filterTypeEl = document.getElementById('symptomFilterType');
    let filterDateEl = document.getElementById('symptomFilterDate');
    let filterType = filterTypeEl ? filterTypeEl.value : '';
    let filterDate = filterDateEl ? filterDateEl.value : '';

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
function initCycleButtons() {
    const addCycleBtn = document.getElementById('addCycleBtn');
    if (addCycleBtn) {
        addCycleBtn.addEventListener('click', function() {
            editCycleId = null;
            document.getElementById('editCycleId').value = '';
            document.getElementById('cycleStartDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('cycleEndDate').value = '';
            document.getElementById('cycleFlow').value = 'medium';
            document.getElementById('cycleNotes').value = '';
            document.getElementById('cycleModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    }
    
    const saveCycleBtn = document.getElementById('saveCycleBtn');
    if (saveCycleBtn) {
        saveCycleBtn.addEventListener('click', function() {
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
    }
}

function closeCycleModal() {
    document.getElementById('cycleModal').style.display = 'none';
    document.body.style.overflow = '';
    editCycleId = null;
}

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
function initSymptomButtons() {
    const addSymptomBtn = document.getElementById('addSymptomBtn');
    if (addSymptomBtn) {
        addSymptomBtn.addEventListener('click', function() {
            editSymptomId = null;
            document.getElementById('editSymptomId').value = '';
            document.getElementById('symptomDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('symptomType').value = 'pain';
            document.getElementById('symptomSeverity').value = 'medium';
            document.getElementById('symptomNotes').value = '';
            document.getElementById('symptomModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    }
    
    const saveSymptomBtn = document.getElementById('saveSymptomBtn');
    if (saveSymptomBtn) {
        saveSymptomBtn.addEventListener('click', function() {
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
    }
}

function closeSymptomModal() {
    document.getElementById('symptomModal').style.display = 'none';
    document.body.style.overflow = '';
    editSymptomId = null;
}

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
function initSymptomFilters() {
    let filterTypeEl = document.getElementById('symptomFilterType');
    let filterDateEl = document.getElementById('symptomFilterDate');
    if (filterTypeEl) filterTypeEl.addEventListener('change', renderSymptoms);
    if (filterDateEl) filterDateEl.addEventListener('change', renderSymptoms);
}

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
// مقداردهی اولیه تمام کامپوننت‌ها
// ============================================
function initAllComponents() {
    initTabs();
    initCycleButtons();
    initSymptomButtons();
    initSymptomFilters();
}

// به‌روزرسانی تابع initHealthApp برای فراخوانی تمام توابع مقداردهی
function initHealthApp() {
    // مقداردهی متغیرها از داده‌های PHP
    cycles = (typeof initialCycles !== 'undefined') ? initialCycles : [];
    symptoms = (typeof initialSymptoms !== 'undefined') ? initialSymptoms : [];
    predictionData = (typeof prediction !== 'undefined') ? prediction : null;
    currentPhase = (typeof currentPhase !== 'undefined') ? currentPhase : 'not_started';
    
    // مقداردهی تمام کامپوننت‌ها
    initAllComponents();
    
    // رندر اولیه
    renderAll();
    
    console.log('Health App initialized with', cycles.length, 'cycles and', symptoms.length, 'symptoms');
}
