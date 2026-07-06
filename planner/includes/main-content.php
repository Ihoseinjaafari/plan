        </div>
    </div>

    <!-- ===== محتوای اصلی ===== -->
    <div class="main-container">
        <input type="hidden" id="serverToday" value="<?php echo $todayTehran; ?>">
        <input type="hidden" id="serverTomorrow" value="<?php echo $tomorrowTehran; ?>">
        <input type="hidden" id="todayJalali" value="<?php echo $todayJalali; ?>">

        <div id="mainApp" class="main-app">
            <div class="container">
                <!-- ===== فیلترها ===== -->
                <div class="filters-card" id="filtersCard">
                    <div class="filter-group">
                        <div class="date-range-group">
                            <!-- Picker تاریخ شمسی برای فیلتر از تاریخ -->
                            <div class="date-picker-wrapper" style="margin-bottom: 0; flex: 1; min-width: 140px;">
                                <input type="text" id="filterDateFrom" class="date-picker-input" placeholder="از تاریخ" readonly style="width: 100%;">
                                <div class="jalali-calendar" id="filterDateFromCalendar"></div>
                            </div>
                            <span>تا</span>
                            <!-- Picker تاریخ شمسی برای فیلتر تا تاریخ -->
                            <div class="date-picker-wrapper" style="margin-bottom: 0; flex: 1; min-width: 140px;">
                                <input type="text" id="filterDateTo" class="date-picker-input" placeholder="تا تاریخ" readonly style="width: 100%;">
                                <div class="jalali-calendar" id="filterDateToCalendar"></div>
                            </div>
                            <button class="apply-date-range" id="applyDateRangeBtn"><span class="icon icon-check"></span> اعمال بازه</button>
                        </div>
                        <select id="filterPriority" class="filter-select">
                            <option value="">همه اولویت‌ها</option>
                            <option value="high">🔴 بالا</option>
                            <option value="medium">🟡 متوسط</option>
                            <option value="low">🟢 پایین</option>
                        </select>
                        <select id="filterCategory" class="filter-select"><option value="">همه دسته‌بندی‌ها</option></select>
                        <select id="filterProject" class="filter-select"><option value="">همه پروژه‌ها</option></select>
                        <button class="clear-filters" onclick="clearFilters()"><span class="icon icon-undo"></span> پاک کردن فیلترها</button>
                    </div>
                </div>

                <!-- ===== آمار ===== -->
                <div class="stats" id="stats"></div>

                <!-- ===== تسک‌ها ===== -->
                <div class="tasks-card">
                    <div class="view-toggle-global">
                        <button class="view-btn-global" id="gridViewBtn">
                            <span class="icon icon-th-large"></span> نمایش کارتی
                        </button>
                        <button class="view-btn-global" id="listViewBtn">
                            <span class="icon icon-list"></span> نمایش لیستی
                        </button>
                    </div>

                    <div class="drag-info">
                        <span class="icon icon-arrows-alt"></span> برای تغییر اولویت، کارها را با ماوس بکشید و جابجا کنید
                    </div>
                    <div id="tasksList"></div>
                </div>
            </div>

