        // ============================================
        // مدیریت منوی موبایل
        // ============================================
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            menu.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = menu.classList.contains('open') ? 'hidden' : '';
        }

        function closeMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            menu.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', toggleMobileMenu);
            }

            document.getElementById('mobileMenuOverlay')?.addEventListener('click', closeMobileMenu);
        });

        // ============================================
        // مدیریت فیلترها در منوی پلنر
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nav-filters .nav-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.nav-filters .nav-btn').forEach(function(b) {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                    if (this.dataset.filter) {
                        currentFilter = this.dataset.filter;
                        renderAll();
                    }
                });
            });

            document.querySelectorAll('.nav-btn-mobile').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var filter = this.dataset.filter;
                    if (filter) {
                        document.querySelectorAll('.nav-filters .nav-btn').forEach(function(b) {
                            b.classList.remove('active');
                        });
                        document.querySelector('.nav-filters .nav-btn[data-filter="' + filter + '"]')?.classList.add('active');
                        document.querySelectorAll('.nav-btn-mobile').forEach(function(b) {
                            b.classList.remove('active-mobile');
                        });
                        this.classList.add('active-mobile');
                        currentFilter = filter;
                        renderAll();
                        closeMobileMenu();
                    }
                });
            });
        });

        // ============================================
        // متغیرها
        // ============================================
        const SERVER_TODAY = document.getElementById('serverToday').value;
        const SERVER_TOMORROW = document.getElementById('serverTomorrow').value;
        const TODAY_JALALI = document.getElementById('todayJalali').value;

        let tasks = [];
        let categories = [];
        let projects = [];
        let currentFilter = 'today';
        let currentEditId = null;
        let sortableInstances = {};
        let filters = { date: '', priority: '', category: '', project: '', dateFrom: '', dateTo: '' };
        let currentView = localStorage.getItem('taskView') || 'grid';
        let currentProjectPage = null;
        let datePickers = {};
        let addTimeSelector = null;
        let editTimeSelector = null;

        // ============================================
        // توابع تبدیل تاریخ شمسی (جاوااسکریپت)
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
                    var today = toJalaliDate(new Date().toISOString().split('T')[0]);
                    this.currentDate = today;
                    this.input.value = today;
                }
                
                var parts = this.currentDate.split('/');
                this.currentYear = parseInt(parts[0]);
                this.currentMonth = parseInt(parts[1]);
                this.currentDay = parseInt(parts[2]);
                
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
        // توابع مدیریت زمان با اسلایدر
        // ============================================
        function initTimeSelector(rangeId, displayId) {
            var range = document.getElementById(rangeId);
            var display = document.getElementById(displayId);
            if (!range || !display) return;
            
            function updateDisplay() {
                var totalMinutes = parseInt(range.value);
                var hours = Math.floor(totalMinutes / 60);
                var minutes = totalMinutes % 60;
                var timeStr = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                display.textContent = timeStr;
            }
            
            range.addEventListener('input', updateDisplay);
            updateDisplay();
            
            return {
                getTime: function() {
                    var totalMinutes = parseInt(range.value);
                    var hours = Math.floor(totalMinutes / 60);
                    var minutes = totalMinutes % 60;
                    return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                },
                setTime: function(timeStr) {
                    if (!timeStr) return;
                    var parts = timeStr.split(':');
                    if (parts.length !== 2) return;
                    var hours = parseInt(parts[0]) || 0;
                    var minutes = parseInt(parts[1]) || 0;
                    var totalMinutes = (hours * 60) + minutes;
                    if (totalMinutes >= 0 && totalMinutes <= 1439) {
                        range.value = totalMinutes;
                        updateDisplay();
                    }
                }
            };
        }

        // ============================================
        // توابع کمکی
        // ============================================
        function toPersianNumbers(str) {
            if (str === undefined || str === null) return '';
            var persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return String(str).replace(/[0-9]/g, function(d) { return persianDigits[parseInt(d)]; });
        }

        function validateTime(timeStr) { return /^([0-1][0-9]|2[0-3]):([0-5][0-9])$/.test(timeStr); }

        function formatTimeToPersian(timeStr) {
            if (!timeStr) return '⏰ --:--';
            if (!validateTime(timeStr)) return '⏰ ۱۲:۰۰';
            var parts = timeStr.split(':');
            return toPersianNumbers(parts[0]) + ':' + toPersianNumbers(parts[1]);
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return toPersianNumbers(d.toLocaleDateString('fa-IR', options));
        }

        function formatDateTime(dateTimeStr) {
            if (!dateTimeStr) return '';
            var d = new Date(dateTimeStr);
            var date = formatDate(d.toISOString().split('T')[0]);
            var time = d.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
            return date + ' ساعت ' + time;
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function isToday(dateStr) { return dateStr === SERVER_TODAY; }
        function isTomorrow(dateStr) { return dateStr === SERVER_TOMORROW; }
        function isPast(dateStr) { return dateStr < SERVER_TODAY; }
        function isUpcoming(dateStr) { return dateStr > SERVER_TODAY && dateStr !== SERVER_TOMORROW; }

        function getTaskChildren(taskId) { return tasks.filter(function(t) { return t.parent_id == taskId; }); }

        function getTaskProgress(taskId) {
            var children = getTaskChildren(taskId);
            if (children.length === 0) return null;
            var total = children.length;
            var done = children.filter(function(t) { return t.done; }).length;
            return { total: total, done: done, percent: Math.round((done / total) * 100) };
        }

        function updateParentSelects() {
            var options = '<option value="">بدون والد (تسک اصلی)</option>';
            var sortedTasks = tasks.slice().sort(function(a, b) { return (a.order || 0) - (b.order || 0); });
            var parentCandidates = sortedTasks.filter(function(t) { return !t.parent_id || t.parent_id === ''; });
            parentCandidates.forEach(function(task) {
                options += '<option value="' + task.id + '">' + escapeHtml(task.title) + '</option>';
            });
            document.getElementById('addParentTask').innerHTML = options;
            document.getElementById('editParentTask').innerHTML = options;
        }

        // ============================================
        // توابع دیتا
        // ============================================
        function loadData() {
            var formData = new FormData();
            formData.append('action', 'load');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        tasks = result.tasks || [];
                        categories = result.categories || [];
                        projects = result.projects || [];
                        updateSelects();
                        updateParentSelects();
                        renderAll();
                        initDatePickers();
                        initTimeSelectors();
                    }
                })['catch'](function(e) {
                    console.error('خطا در ارتباط با سرور:', e);
                });
        }

        function sendRequest(action, data) {
            var formData = new FormData();
            formData.append('action', action);
            for (var key in data) {
                formData.append(key, data[key]);
            }
            return fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        if (result.tasks) {
                            tasks = result.tasks;
                            updateParentSelects();
                        }
                        if (result.categories) {
                            categories = result.categories;
                            updateSelects();
                            if (document.getElementById('categoryModal').style.display === 'block') refreshCategoryList();
                        }
                        if (result.projects) {
                            projects = result.projects;
                            updateSelects();
                            if (document.getElementById('projectModal').style.display === 'block') refreshProjectList();
                        }
                        renderAll();
                    }
                    return result;
                });
        }

        function updateSelects() {
            var categoryOptions = categories.map(function(c) { return '<option value="' + c + '">' + c + '</option>'; }).join('');
            var projectOptions = projects.map(function(p) { return '<option value="' + p.name + '">' + p.name + '</option>'; }).join('');
            document.getElementById('addCategory').innerHTML = categoryOptions;
            document.getElementById('addProject').innerHTML = '<option value="">بدون پروژه</option>' + projectOptions;
            document.getElementById('editCategory').innerHTML = categoryOptions;
            document.getElementById('editProject').innerHTML = '<option value="">بدون پروژه</option>' + projectOptions;
            document.getElementById('filterCategory').innerHTML = '<option value="">همه دسته‌بندی‌ها</option>' + categoryOptions;
            document.getElementById('filterProject').innerHTML = '<option value="">همه پروژه‌ها</option>' + projectOptions;
        }

        function refreshCategoryList() {
            var container = document.getElementById('categoryList');
            if (!categories || categories.length === 0) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-light);">هیچ دسته‌بندی تعریف نشده است</div>';
            } else {
                container.innerHTML = categories.map(function(cat) {
                    return '<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);"><span style="color:var(--text-primary);">' + cat + '</span><button onclick="deleteCategory(\'' + cat + '\')" style="background:rgba(220,53,69,0.15); color:#ff6b6b; padding:4px 10px; border:none; border-radius:6px; cursor:pointer;">🗑️</button></div>';
                }).join('');
            }
        }

        function refreshProjectList() {
            var container = document.getElementById('projectList');
            if (!projects || projects.length === 0) {
                container.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-light);">هیچ پروژه‌ای تعریف نشده است</div>';
            } else {
                container.innerHTML = projects.map(function(proj) {
                    return '<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);"><a onclick="openProjectPage(\'' + encodeURIComponent(proj.name) + '\')" style="cursor:pointer; color:var(--text-primary); text-decoration:none; display:flex; align-items:center; gap:8px;"><span class="icon icon-project" style="color: ' + (proj.color || '#2d6a4f') + '"></span> ' + escapeHtml(proj.name) + '</a><button onclick="deleteProject(\'' + proj.name + '\')" style="background:rgba(220,53,69,0.15); color:#ff6b6b; padding:4px 10px; border:none; border-radius:6px; cursor:pointer;">🗑️</button></div>';
                }).join('');
            }
        }

        // ============================================
        // مدیریت Picker تاریخ شمسی
        // ============================================
        function initDatePickers() {
            var addPicker = new JalaliDatePicker('addDate', 'addDateCalendar', {
                defaultDate: TODAY_JALALI,
                onSelect: function(dateStr) {
                    document.getElementById('addDate').value = dateStr;
                }
            });
            datePickers['addDateCalendar'] = addPicker;

            var editPicker = new JalaliDatePicker('editDate', 'editDateCalendar', {
                defaultDate: TODAY_JALALI,
                onSelect: function(dateStr) {
                    document.getElementById('editDate').value = dateStr;
                }
            });
            datePickers['editDateCalendar'] = editPicker;

            window.datePickers = datePickers;
        }

        // ============================================
        // مدیریت زمان با اسلایدر
        // ============================================
        function initTimeSelectors() {
            addTimeSelector = initTimeSelector('addTimeRange', 'addTimeDisplay');
            editTimeSelector = initTimeSelector('editTimeRange', 'editTimeDisplay');
        }

        function getTimeFromSelector(selector) {
            return selector ? selector.getTime() : '12:00';
        }

        // ============================================
        // توابع فیلتر و رندر
        // ============================================
        function getFilteredTasks() {
            var filtered = tasks.slice();

            if (currentFilter === 'completed') {
                filtered = filtered.filter(function(t) { return t.done === true; });
                if (filters.dateFrom && filters.dateTo) {
                    filtered = filtered.filter(function(t) { return t.date >= filters.dateFrom && t.date <= filters.dateTo; });
                }
                if (filters.priority) filtered = filtered.filter(function(t) { return t.priority === filters.priority; });
                if (filters.category) filtered = filtered.filter(function(t) { return t.category === filters.category; });
                if (filters.project) filtered = filtered.filter(function(t) { return t.project === filters.project; });
            } else {
                switch(currentFilter) {
                    case 'today': filtered = filtered.filter(function(t) { return isToday(t.date) && !t.done; }); break;
                    case 'tomorrow': filtered = filtered.filter(function(t) { return isTomorrow(t.date) && !t.done; }); break;
                    case 'upcoming': filtered = filtered.filter(function(t) { return isUpcoming(t.date) && !t.done; }); break;
                    case 'past': filtered = filtered.filter(function(t) { return isPast(t.date) && !t.done; }); break;
                    case 'all': break;
                }
            }

            filtered.sort(function(a, b) { return (a.order || 0) - (b.order || 0); });
            return filtered;
        }

        function groupByDate(tasksList) {
            var groups = {};
            tasksList.forEach(function(task) {
                if (!groups[task.date]) groups[task.date] = [];
                groups[task.date].push(task);
            });
            return groups;
        }

        function updateStats() {
            var total = tasks.length;
            var completed = tasks.filter(function(t) { return t.done; }).length;
            var todayTasks = tasks.filter(function(t) { return isToday(t.date) && !t.done; }).length;
            var upcoming = tasks.filter(function(t) { return !isPast(t.date) && !t.done && !isToday(t.date); }).length;
            var parentTasks = tasks.filter(function(t) { return !t.parent_id || t.parent_id === ''; }).length;
            var subtasks = tasks.filter(function(t) { return t.parent_id && t.parent_id !== ''; }).length;

            var tasksWithProgress = tasks.filter(function(t) { return getTaskChildren(t.id).length > 0; });
            var avgProgress = 0;
            if (tasksWithProgress.length > 0) {
                var totalProgress = 0;
                tasksWithProgress.forEach(function(t) {
                    var p = getTaskProgress(t.id);
                    if (p) totalProgress += p.percent;
                });
                avgProgress = Math.round(totalProgress / tasksWithProgress.length);
            }

            document.getElementById('stats').innerHTML = 
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(total) + '</div><div class="stat-label">کل وظایف</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(completed) + '</div><div class="stat-label">انجام شده</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(todayTasks) + '</div><div class="stat-label">وظایف امروز</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(upcoming) + '</div><div class="stat-label">روزهای آینده</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(parentTasks) + '</div><div class="stat-label">تسک‌های اصلی</div></div>' +
                '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(subtasks) + '</div><div class="stat-label">زیرتسک‌ها</div></div>' +
                (tasksWithProgress.length > 0 ? '<div class="stat-card"><div class="stat-number">' + toPersianNumbers(avgProgress) + '%</div><div class="stat-label">میانگین پیشرفت</div></div>' : '');
        }

        function setView(view) {
            currentView = view;
            localStorage.setItem('taskView', view);

            if (view === 'grid') {
                document.getElementById('gridViewBtn').classList.add('active');
                document.getElementById('listViewBtn').classList.remove('active');
            } else {
                document.getElementById('gridViewBtn').classList.remove('active');
                document.getElementById('listViewBtn').classList.add('active');
            }
            renderTasks();
        }

        function renderProgressBar(taskId) {
            var progress = getTaskProgress(taskId);
            if (!progress) return '';
            return '<div class="progress-container"><div class="progress-bar" style="width: ' + progress.percent + '%"></div></div><div class="progress-text"><span>پیشرفت</span><span>' + toPersianNumbers(progress.done) + ' از ' + toPersianNumbers(progress.total) + ' (' + toPersianNumbers(progress.percent) + '%)</span></div>';
        }

        function renderSubtasks(taskId, parentTitle) {
            var children = tasks.filter(function(t) { return t.parent_id == taskId; });
            if (children.length === 0) return '';
            return '<div class="subtasks-container"><div style="font-size: 12px; font-weight: bold; margin-bottom: 6px; color: #667eea;"><span class="icon icon-sitemap"></span> زیرتسک‌ها (' + children.length + ')</div>' + 
                children.map(function(child) {
                    return '<div class="subtask-item"><input type="checkbox" class="subtask-check" ' + (child.done ? 'checked' : '') + ' onchange="toggleTask(\'' + child.id + '\', ' + child.done + ')"><span class="subtask-title-text ' + (child.done ? 'completed' : '') + '">' + escapeHtml(child.title) + '</span><div class="subtask-actions"><button onclick="openEditModal(\'' + child.id + '\')"><span class="icon icon-edit"></span></button><button onclick="deleteTask(\'' + child.id + '\')"><span class="icon icon-trash" style="color:#ff6b6b;"></span></button></div></div>';
                }).join('') +
                '<div style="margin-top: 6px;"><button class="subtask-btn" onclick="openAddSubtaskModal(\'' + taskId + '\', \'' + escapeHtml(parentTitle) + '\')"><span class="icon icon-plus"></span> افزودن زیرتسک</button></div></div>';
        }

        function renderGridTasks(tasksList) {
            var mainTasks = tasksList.filter(function(t) { return !t.parent_id || t.parent_id === ''; });
            if (mainTasks.length === 0) {
                return '<div class="cards-grid"><div class="empty-state">هیچ تسک اصلی یافت نشد</div></div>';
            }
            return '<div class="cards-grid sortable-grid">' + 
                mainTasks.map(function(task) {
                    var progress = getTaskProgress(task.id);
                    var isCompleted = task.done;
                    return '<div class="task-card ' + (isCompleted ? 'completed' : '') + '" data-id="' + task.id + '">' +
                        '<div class="card-drag-handle"><span class="icon icon-grip-vertical"></span></div>' +
                        '<input type="checkbox" class="card-check" ' + (isCompleted ? 'checked' : '') + ' onchange="toggleTask(\'' + task.id + '\', ' + isCompleted + ')">' +
                        '<div class="card-content">' +
                            '<div class="task-header-row"><div class="task-title-text ' + (isCompleted ? 'completed' : '') + '"><a href="#" class="task-link" onclick="event.preventDefault();">' + escapeHtml(task.title) + '</a><span class="subtask-badge"><span class="icon icon-sitemap"></span> ' + getTaskChildren(task.id).length + '</span></div></div>' +
                            (task.description ? '<div class="task-description"><span class="icon icon-align-left"></span> ' + escapeHtml(task.description) + '</div>' : '') +
                            (progress ? renderProgressBar(task.id) : '') +
                            '<div class="task-meta"><span class="time-badge"><span class="icon icon-clock"></span> ' + formatTimeToPersian(task.time) + '</span><span class="category-badge"><span class="icon icon-tag"></span> ' + task.category + '</span>' + (task.project ? '<span class="project-badge"><span class="icon icon-project"></span> ' + task.project + '</span>' : '') + '<span class="priority-badge priority-' + task.priority + '">' + (task.priority === 'high' ? '🔴 بالا' : task.priority === 'medium' ? '🟡 متوسط' : '🟢 پایین') + '</span></div>' +
                            (task.completed_at ? '<div class="completed-date"><span class="icon icon-check-circle"></span> ' + formatDateTime(task.completed_at) + '</div>' : '') +
                            renderSubtasks(task.id, task.title) +
                        '</div>' +
                        '<div class="card-actions"><button class="card-btn edit-card-btn" onclick="openEditModal(\'' + task.id + '\')"><span class="icon icon-edit"></span> ویرایش</button><button class="card-btn delete-card-btn" onclick="deleteTaskWithChildren(\'' + task.id + '\')"><span class="icon icon-trash"></span> حذف</button></div>' +
                    '</div>';
                }).join('') +
            '</div>';
        }

        function renderListTasks(tasksList) {
            var mainTasks = tasksList.filter(function(t) { return !t.parent_id || t.parent_id === ''; });
            if (mainTasks.length === 0) {
                return '<div class="empty-state">هیچ تسک اصلی یافت نشد</div>';
            }
            return '<div class="list-view-container sortable-list">' +
                mainTasks.map(function(task) {
                    var progress = getTaskProgress(task.id);
                    return '<div class="task-item-list ' + (task.done ? 'completed' : '') + '" data-id="' + task.id + '">' +
                        '<div class="drag-handle-list"><span class="icon icon-grip-vertical"></span></div>' +
                        '<input type="checkbox" class="task-check-list" ' + (task.done ? 'checked' : '') + ' onchange="toggleTask(\'' + task.id + '\', ' + task.done + ')">' +
                        '<div class="task-content-list">' +
                            '<div class="task-title-list"><a href="#" class="task-link" onclick="event.preventDefault();">' + escapeHtml(task.title) + '</a><span class="subtask-badge"><span class="icon icon-sitemap"></span> ' + getTaskChildren(task.id).length + '</span></div>' +
                            (progress ? renderProgressBar(task.id) : '') +
                            '<div class="task-meta-list"><span class="time-badge"><span class="icon icon-clock"></span> ' + formatTimeToPersian(task.time) + '</span><span class="category-badge"><span class="icon icon-tag"></span> ' + task.category + '</span>' + (task.project ? '<span class="project-badge"><span class="icon icon-project"></span> ' + task.project + '</span>' : '') + '<span class="priority-badge priority-' + task.priority + '">' + (task.priority === 'high' ? '🔴 بالا' : task.priority === 'medium' ? '🟡 متوسط' : '🟢 پایین') + '</span></div>' +
                            (task.description ? '<div style="font-size: 12px; color: var(--text-muted); margin-top: 5px;"><span class="icon icon-align-left"></span> ' + escapeHtml(task.description) + '</div>' : '') +
                            (task.completed_at ? '<div style="font-size: 11px; color: var(--completed-date); margin-top: 5px;"><span class="icon icon-check-circle"></span> انجام شده در ' + formatDateTime(task.completed_at) + '</div>' : '') +
                            renderSubtasks(task.id, task.title) +
                        '</div>' +
                        '<div class="task-actions-list"><button class="edit-btn-list" onclick="openEditModal(\'' + task.id + '\')"><span class="icon icon-edit"></span></button><button class="delete-btn-list" onclick="deleteTaskWithChildren(\'' + task.id + '\')"><span class="icon icon-trash"></span></button></div>' +
                    '</div>';
                }).join('') +
            '</div>';
        }

        function renderTasks() {
            var filtered = getFilteredTasks();
            var grouped = groupByDate(filtered);
            var sortedDates = Object.keys(grouped).sort().reverse();
            var container = document.getElementById('tasksList');

            if (sortedDates.length === 0) {
                container.innerHTML = '<div class="empty-state"><span class="icon icon-inbox" style="font-size: 48px;"></span><div>هیچ کاری یافت نشد</div></div>';
                updateStats();
                return;
            }

            container.innerHTML = sortedDates.map(function(date) {
                return '<div class="date-group">' +
                    '<div class="date-header"><span><span class="icon icon-calendar-alt"></span> ' + formatDate(date) + '</span><span>' + toPersianNumbers(grouped[date].length) + ' کار</span></div>' +
                    '<div class="tasks-container-' + currentView + '">' +
                    (currentView === 'grid' ? renderGridTasks(grouped[date]) : renderListTasks(grouped[date])) +
                    '</div></div>';
            }).join('');

            initSortables();
            updateStats();
        }

        function initSortables() {
            for (var id in sortableInstances) sortableInstances[id].destroy();
            sortableInstances = {};

            document.querySelectorAll('.sortable-grid').forEach(function(grid) {
                sortableInstances[grid.id] = new Sortable(grid, {
                    animation: 300,
                    handle: '.card-drag-handle',
                    ghostClass: 'dragging',
                    onEnd: function() {
                        var allIds = [];
                        document.querySelectorAll('.sortable-grid').forEach(function(g) {
                            g.querySelectorAll('.task-card').forEach(function(card) {
                                var id = card.getAttribute('data-id');
                                if (id) allIds.push(id);
                            });
                        });
                        if (allIds.length > 0) sendRequest('reorder', { ids: JSON.stringify(allIds) });
                    }
                });
            });

            document.querySelectorAll('.sortable-list').forEach(function(list) {
                sortableInstances[list.id] = new Sortable(list, {
                    animation: 300,
                    handle: '.drag-handle-list',
                    ghostClass: 'dragging',
                    onEnd: function() {
                        var allIds = [];
                        document.querySelectorAll('.sortable-list').forEach(function(l) {
                            l.querySelectorAll('.task-item-list').forEach(function(item) {
                                var id = item.getAttribute('data-id');
                                if (id) allIds.push(id);
                            });
                        });
                        if (allIds.length > 0) sendRequest('reorder', { ids: JSON.stringify(allIds) });
                    }
                });
            });
        }

        // ============================================
        // توابع عملیات تسک
        // ============================================
        function toggleTask(id, currentDone) {
            sendRequest('toggle', { id: id, current_done: currentDone });
            if (currentProjectPage) {
                openProjectPage(encodeURIComponent(currentProjectPage));
            }
        }

        function deleteTask(id) {
            if (confirm('حذف شود؟')) sendRequest('delete', { id: id });
        }

        function deleteTaskWithChildren(id) {
            var task = tasks.find(function(t) { return t.id == id; });
            var children = getTaskChildren(id);
            if (children.length > 0) {
                if (confirm('تسک "' + (task ? task.title : '') + '" دارای ' + children.length + ' زیرتسک است.\nآیا می‌خواهید همه آن‌ها را حذف کنید؟')) {
                    sendRequest('delete', { id: id, delete_children: 'true' });
                }
            } else {
                if (confirm('حذف شود؟')) sendRequest('delete', { id: id });
            }
        }

        function openAddTaskModal() {
            document.getElementById('addTitle').value = '';
            document.getElementById('addDescription').value = '';
            document.getElementById('addDate').value = '';
            if (addTimeSelector) {
                addTimeSelector.setTime('');
            }
            document.getElementById('addPriority').value = 'medium';
            document.getElementById('addParentTask').value = '';
            document.getElementById('addTaskModal').style.display = 'block';
            document.body.style.overflow = 'hidden';

            if (datePickers['addDateCalendar']) {
                var parts = TODAY_JALALI.split('/');
                datePickers['addDateCalendar'].currentYear = parseInt(parts[0]);
                datePickers['addDateCalendar'].currentMonth = parseInt(parts[1]);
                datePickers['addDateCalendar'].currentDay = parseInt(parts[2]);
                datePickers['addDateCalendar'].currentDate = TODAY_JALALI;
                datePickers['addDateCalendar'].render();
            }
        }

        function openAddSubtaskModal(parentId, parentTitle) {
            document.getElementById('addTitle').value = '';
            document.getElementById('addDescription').value = '';
            document.getElementById('addDate').value = TODAY_JALALI;
            if (addTimeSelector) {
                addTimeSelector.setTime('12:00');
            }
            document.getElementById('addPriority').value = 'medium';
            document.getElementById('addParentTask').value = parentId;

            if (datePickers['addDateCalendar']) {
                var parts = TODAY_JALALI.split('/');
                datePickers['addDateCalendar'].currentYear = parseInt(parts[0]);
                datePickers['addDateCalendar'].currentMonth = parseInt(parts[1]);
                datePickers['addDateCalendar'].currentDay = parseInt(parts[2]);
                datePickers['addDateCalendar'].currentDate = TODAY_JALALI;
                datePickers['addDateCalendar'].render();
            }

            document.querySelector('#addTaskModal .modal-header').innerHTML = '<span class="icon icon-plus-circle"></span> افزودن زیرتسک برای "' + escapeHtml(parentTitle) + '"';
            document.getElementById('addTaskModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeAddTaskModal() {
            document.getElementById('addTaskModal').style.display = 'none';
            document.body.style.overflow = '';
            document.querySelector('#addTaskModal .modal-header').innerHTML = '<span class="icon icon-plus-circle"></span> افزودن کار جدید';
        }

        function addNewTask() {
            var title = document.getElementById('addTitle').value.trim();
            if (!title) { alert('لطفاً عنوان کار را وارد کنید'); return; }

            var timeValue = getTimeFromSelector(addTimeSelector);
            if (!validateTime(timeValue)) { alert('فرمت زمان صحیح نیست'); return; }

            var jalaliDate = document.getElementById('addDate').value;
            var gregorianDate = toGregorianDate(jalaliDate) || SERVER_TODAY;
            var parentId = document.getElementById('addParentTask').value;

            sendRequest('add', {
                title: title,
                description: document.getElementById('addDescription').value,
                category: document.getElementById('addCategory').value,
                project: document.getElementById('addProject').value,
                date: gregorianDate,
                time: timeValue,
                priority: document.getElementById('addPriority').value,
                parent_id: parentId
            }).then(function() {
                closeAddTaskModal();
                document.querySelector('#addTaskModal .modal-header').innerHTML = '<span class="icon icon-plus-circle"></span> افزودن کار جدید';
            });
        }

        function openEditModal(id) {
            var task = tasks.find(function(t) { return t.id == id; });
            if (!task) return;
            currentEditId = id;
            document.getElementById('editTitle').value = task.title;
            document.getElementById('editCategory').value = task.category;
            document.getElementById('editProject').value = task.project || '';

            var jalaliDate = toJalaliDate(task.date);
            document.getElementById('editDate').value = jalaliDate;
            if (datePickers['editDateCalendar']) {
                var parts = jalaliDate.split('/');
                datePickers['editDateCalendar'].currentYear = parseInt(parts[0]);
                datePickers['editDateCalendar'].currentMonth = parseInt(parts[1]);
                datePickers['editDateCalendar'].currentDay = parseInt(parts[2]);
                datePickers['editDateCalendar'].currentDate = jalaliDate;
                datePickers['editDateCalendar'].render();
            }

            if (editTimeSelector) {
                editTimeSelector.setTime(task.time || '12:00');
            }
            document.getElementById('editPriority').value = task.priority;
            document.getElementById('editDescription').value = task.description || '';
            document.getElementById('editParentTask').value = task.parent_id || '';
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            currentEditId = null;
            document.body.style.overflow = '';
        }

        function saveEdit() {
            if (!currentEditId) return;
            var timeValue = getTimeFromSelector(editTimeSelector);
            if (!validateTime(timeValue)) { alert('فرمت زمان صحیح نیست'); return; }

            var jalaliDate = document.getElementById('editDate').value;
            var gregorianDate = toGregorianDate(jalaliDate) || SERVER_TODAY;

            sendRequest('edit', {
                id: currentEditId,
                title: document.getElementById('editTitle').value,
                description: document.getElementById('editDescription').value,
                category: document.getElementById('editCategory').value,
                project: document.getElementById('editProject').value,
                date: gregorianDate,
                time: timeValue,
                priority: document.getElementById('editPriority').value,
                parent_id: document.getElementById('editParentTask').value
            }).then(function() {
                closeEditModal();
            });
        }

        // ============================================
        // توابع مدیریت دسته و پروژه
        // ============================================
        function openCategoryModal() {
            refreshCategoryList();
            document.getElementById('categoryModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function addCategory() {
            var newCat = document.getElementById('newCategoryName').value.trim();
            if (newCat && !categories.includes(newCat)) {
                sendRequest('add_category', { category: newCat });
                document.getElementById('newCategoryName').value = '';
            } else if (newCat && categories.includes(newCat)) {
                alert('این دسته بندی قبلاً وجود دارد');
            } else {
                alert('لطفاً نام دسته بندی را وارد کنید');
            }
        }

        function deleteCategory(category) {
            if (confirm('حذف دسته "' + category + '"؟')) {
                sendRequest('delete_category', { category: category });
            }
        }

        function openProjectModal() {
            refreshProjectList();
            document.getElementById('projectModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeProjectModal() {
            document.getElementById('projectModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function addProject() {
            var newProj = document.getElementById('newProjectName').value.trim();
            if (!newProj) {
                alert('لطفاً نام پروژه را وارد کنید');
                return;
            }
            if (projects.some(function(p) { return p.name === newProj; })) {
                alert('این پروژه قبلاً وجود دارد');
                return;
            }
            sendRequest('add_project', { project: newProj }).then(function(result) {
                if (result && !result.success && result.message) {
                    alert(result.message);
                } else {
                    document.getElementById('newProjectName').value = '';
                }
            });
        }

        function deleteProject(project) {
            if (confirm('آیا از حذف پروژه "' + project + '" مطمئن هستید؟\nتوجه: تسک‌های این پروژه به "بدون پروژه" تغییر می‌یابند.')) {
                sendRequest('delete_project', { project: project }).then(function() {
                    closeProjectModal();
                });
            }
        }

        // ============================================
        // صفحه پروژه
        // ============================================
        function openProjectPage(projectName) {
            var decodedName = decodeURIComponent(projectName);
            var project = projects.find(function(p) { return p.name === decodedName; });
            if (!project) {
                alert('پروژه یافت نشد');
                return;
            }
            currentProjectPage = decodedName;
            document.getElementById('projectPageTitle').innerHTML = project.name;
            var descText = project.description || 'هنوز توضیحاتی ثبت نشده است';
            document.getElementById('projectPageDesc').innerHTML = escapeHtml(descText).replace(/\n/g, '<br>');

            var projectTasks = tasks.filter(function(t) { return t.project === decodedName; });
            var total = projectTasks.length;
            var completed = projectTasks.filter(function(t) { return t.done; }).length;
            var pending = total - completed;
            var percent = total > 0 ? Math.round((completed / total) * 100) : 0;

            document.getElementById('projectPageStats').innerHTML = 
                '<div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #43e97b;">' + toPersianNumbers(total) + '</div><div>کل تسک‌ها</div></div>' +
                '<div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #43e97b;">' + toPersianNumbers(completed) + '</div><div>انجام شده</div></div>' +
                '<div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #f5576c;">' + toPersianNumbers(pending) + '</div><div>در انتظار</div></div>' +
                '<div class="project-stat-card"><div style="font-size: 22px; font-weight: bold; color: #667eea;">' + toPersianNumbers(percent) + '%</div><div>پیشرفت</div></div>';

            if (projectTasks.length === 0) {
                document.getElementById('projectPageTasks').innerHTML = '<div style="text-align:center; padding:30px; color:var(--text-light);">هیچ تسکی برای این پروژه وجود ندارد</div>';
            } else {
                document.getElementById('projectPageTasks').innerHTML = projectTasks.map(function(task) {
                    var progress = getTaskProgress(task.id);
                    var progressText = progress ? ' | پیشرفت: ' + progress.done + '/' + progress.total + ' (' + progress.percent + '%)' : '';
                    return '<div class="project-task-item"><a href="#" class="task-link" onclick="event.preventDefault();"><div class="task-title ' + (task.done ? 'done' : '') + '">' + escapeHtml(task.title) + (progress ? '<span style="font-size: 11px; color: var(--completed-date);"> (' + progress.percent + '%)</span>' : '') + '</div><div class="task-meta"><span class="icon icon-calendar-alt"></span> ' + formatDate(task.date) + ' - ' + formatTimeToPersian(task.time) + '<span style="background: rgba(245,87,108,0.15); color:#f5576c; padding: 2px 6px; border-radius: 10px; margin-right: 8px;"><span class="icon icon-tag"></span> ' + task.category + '</span>' + (task.parent_id ? '<span style="background: var(--badge-bg); color: var(--badge-color); padding: 2px 6px; border-radius: 10px;"><span class="icon icon-sitemap"></span> زیرتسک</span>' : '') + (task.description ? '<span style="color: var(--text-light); margin-right: 8px;"><span class="icon icon-align-left"></span> ' + escapeHtml(task.description.substring(0, 30)) + (task.description.length > 30 ? '...' : '') + '</span>' : '') + progressText + (task.completed_at ? '<span style="color: var(--completed-date); margin-right: 8px; font-size: 10px;"><span class="icon icon-check-circle"></span> ' + formatDateTime(task.completed_at) + '</span>' : '') + '</div></a><div class="task-actions"><span class="priority-badge priority-' + task.priority + '" style="font-size: 11px; padding: 2px 8px; border-radius: 10px;">' + (task.priority === 'high' ? '🔴 بالا' : task.priority === 'medium' ? '🟡 متوسط' : '🟢 پایین') + '</span><input type="checkbox" ' + (task.done ? 'checked' : '') + ' onchange="toggleTaskFromProject(\'' + task.id + '\')" style="width: 22px; height: 22px; cursor: pointer; accent-color: #667eea;"></div></div>';
                }).join('');
            }
            document.getElementById('projectPageModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeProjectPageModal() {
            document.getElementById('projectPageModal').style.display = 'none';
            document.body.style.overflow = '';
            currentProjectPage = null;
        }

        function toggleTaskFromProject(id) {
            var task = tasks.find(function(t) { return t.id == id; });
            if (task) {
                toggleTask(id, task.done);
                if (currentProjectPage) {
                    openProjectPage(encodeURIComponent(currentProjectPage));
                }
            }
        }

        function editProjectDescription() {
            if (!currentProjectPage) return;
            var currentDesc = document.getElementById('projectPageDesc').innerText;
            var newDesc = prompt('توضیحات جدید را وارد کنید:', currentDesc);
            if (newDesc !== null && newDesc !== currentDesc) {
                sendRequest('update_project_description', {
                    name: currentProjectPage,
                    description: newDesc
                }).then(function() {
                    document.getElementById('projectPageDesc').innerHTML = escapeHtml(newDesc).replace(/\n/g, '<br>');
                    var project = projects.find(function(p) { return p.name === currentProjectPage; });
                    if (project) project.description = newDesc;
                });
            }
        }

        // ============================================
        // توابع فیلتر
        // ============================================
        function applyDateRange() {
            var fromDate = document.getElementById('filterDateFrom').value;
            var toDate = document.getElementById('filterDateTo').value;
            if (fromDate && toDate) {
                filters.dateFrom = fromDate;
                filters.dateTo = toDate;
                renderTasks();
            } else {
                alert('لطفاً هر دو تاریخ را انتخاب کنید');
            }
        }

        function clearFilters() {
            filters = { date: '', priority: '', category: '', project: '', dateFrom: '', dateTo: '' };
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            document.getElementById('filterPriority').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('filterProject').value = '';
            renderTasks();
        }

        function setupFilters() {
            var filtersCard = document.getElementById('filtersCard');
            if (currentFilter === 'completed') {
                filtersCard.style.display = 'block';
            } else {
                filtersCard.style.display = 'none';
                clearFilters();
            }
        }

        function renderAll() {
            setupFilters();
            renderTasks();
        }

        // ============================================
        // Event Listeners
        // ============================================
        document.getElementById('openAddTaskBtn')?.addEventListener('click', openAddTaskModal);
        document.getElementById('saveAddTaskBtn')?.addEventListener('click', addNewTask);
        document.getElementById('addCategoryBtn')?.addEventListener('click', addCategory);
        document.getElementById('addProjectBtn')?.addEventListener('click', addProject);
        document.getElementById('editProjectDescBtn')?.addEventListener('click', editProjectDescription);
        document.getElementById('exportCsvBtn')?.addEventListener('click', exportCSV);
        document.getElementById('applyDateRangeBtn')?.addEventListener('click', applyDateRange);
        document.getElementById('changePasswordBtn')?.addEventListener('click', changePassword);

        document.getElementById('gridViewBtn')?.addEventListener('click', function() { setView('grid'); });
        document.getElementById('listViewBtn')?.addEventListener('click', function() { setView('list'); });

        document.getElementById('filterPriority')?.addEventListener('change', function(e) {
            filters.priority = e.target.value;
            renderTasks();
        });
        document.getElementById('filterCategory')?.addEventListener('change', function(e) {
            filters.category = e.target.value;
            renderTasks();
        });
        document.getElementById('filterProject')?.addEventListener('change', function(e) {
            filters.project = e.target.value;
            renderTasks();
        });

        // ============================================
        // خروج و خروجی
        // ============================================
        function logout() {
            if (confirm('آیا از خروج مطمئن هستید؟')) {
                var formData = new FormData();
                formData.append('action', 'logout');
                fetch(window.location.href, { method: 'POST', body: formData }).then(function() {
                    location.href = '../index.php';
                });
            }
        }

        function exportCSV() {
            var formData = new FormData();
            formData.append('action', 'export_csv');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        var blob = new Blob(["\uFEFF" + result.data], { type: 'text/csv;charset=utf-8;' });
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'tasks_' + new Date().toISOString().split('T')[0] + '.csv';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    } else {
                        alert('خطا در ایجاد خروجی CSV');
                    }
                })['catch'](function(e) {
                    console.error('خطا:', e);
                    alert('خطا در ارتباط با سرور');
                });
        }

        // ============================================
        // پروفایل
        // ============================================
        function openProfileModal() {
            document.getElementById('profileModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeProfileModal() {
            document.getElementById('profileModal').style.display = 'none';
            document.body.style.overflow = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmNewPassword').value = '';
            document.getElementById('passwordError').style.display = 'none';
        }

        function changePassword() {
            var newPassword = document.getElementById('newPassword').value;
            var confirmPassword = document.getElementById('confirmNewPassword').value;
            var errorDiv = document.getElementById('passwordError');

            if (!newPassword || !confirmPassword) {
                errorDiv.innerText = 'لطفاً رمز عبور جدید را وارد کنید';
                errorDiv.style.display = 'block';
                return;
            }
            if (newPassword.length < 4) {
                errorDiv.innerText = 'رمز عبور باید حداقل ۴ کاراکتر باشد';
                errorDiv.style.display = 'block';
                return;
            }
            if (newPassword !== confirmPassword) {
                errorDiv.innerText = 'رمز عبور و تکرار آن مطابقت ندارند';
                errorDiv.style.display = 'block';
                return;
            }

            var formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('new_password', newPassword);
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (result.success) {
                        alert('رمز عبور با موفقیت تغییر کرد');
                        closeProfileModal();
                    } else {
                        errorDiv.innerText = result.message || 'خطا در تغییر رمز عبور';
                        errorDiv.style.display = 'block';
                    }
                });
        }

        // ============================================
        // بستن مودال‌ها
        // ============================================
        window.onclick = function(event) {
            if (event.target === document.getElementById('addTaskModal')) {
                closeAddTaskModal();
                document.querySelector('#addTaskModal .modal-header').innerHTML = '<span class="icon icon-plus-circle"></span> افزودن کار جدید';
            }
            if (event.target === document.getElementById('editModal')) closeEditModal();
            if (event.target === document.getElementById('categoryModal')) closeCategoryModal();
            if (event.target === document.getElementById('projectModal')) closeProjectModal();
            if (event.target === document.getElementById('projectPageModal')) closeProjectPageModal();
            if (event.target === document.getElementById('profileModal')) closeProfileModal();
        };

        // ============================================
        // مقداردهی اولیه
        // ============================================
        if (currentView === 'grid') {
            document.getElementById('gridViewBtn')?.classList.add('active');
        } else {
            document.getElementById('listViewBtn')?.classList.add('active');
        }

        // فعال کردن دکمه امروز در منوی پلنر
        document.querySelector('.nav-filters .nav-btn[data-filter="today"]')?.classList.add('active');

        loadData();
