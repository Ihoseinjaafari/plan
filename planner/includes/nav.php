    <div class="planner-nav">
        <div class="planner-nav-content">
            <div class="nav-filters">
                <button class="nav-btn" data-filter="today"><span class="icon icon-calendar-day"></span> امروز</button>
                <button class="nav-btn" data-filter="tomorrow"><span class="icon icon-calendar-plus"></span> فردا</button>
                <button class="nav-btn" data-filter="upcoming"><span class="icon icon-calendar-week"></span> آینده</button>
                <button class="nav-btn" data-filter="past"><span class="icon icon-calendar-minus"></span> گذشته</button>
                <button class="nav-btn" data-filter="completed"><span class="icon icon-check-circle"></span> انجام</button>
                <button class="nav-btn" data-filter="all"><span class="icon icon-list"></span> همه</button>
            </div>
            <div class="nav-actions">
                
                <button class="btn-menu" onclick="openCategoryModal()">
                    <span class="icon icon-tag"></span> دسته‌ها
                </button>
                <button class="btn-menu" onclick="location.href='habits.php'">
                    <span class="icon icon-fire"></span> عادت‌ها
                </button>
                <button class="btn-menu" id="exportCsvBtn">
                    <span class="icon icon-file-csv"></span> CSV
                </button>
                <?php if ($currentUser && $currentUser['email'] === 'admin@example.com'): ?>
                    <a href="admin.php" class="btn-menu">
                        <span class="icon icon-shield-alt"></span> مدیریت
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== منوی موبایل ===== -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <div class="user-info-mobile">
                <div class="user-avatar-mobile" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                    <?php echo mb_substr($currentUser['name'], 0, 1); ?>
                </div>
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                    <div class="email"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                </div>
            </div>
            <button class="mobile-menu-close" onclick="closeMobileMenu()">
                <span class="icon icon-close"></span>
            </button>
        </div>

        <div class="menu-section">
            <div class="menu-section-title"><span class="icon icon-filter"></span> فیلترها</div>
            <div class="menu-section-buttons">
                <button class="nav-btn-mobile" data-filter="today"><span class="icon icon-calendar-day"></span> امروز</button>
                <button class="nav-btn-mobile" data-filter="tomorrow"><span class="icon icon-calendar-plus"></span> فردا</button>
                <button class="nav-btn-mobile" data-filter="upcoming"><span class="icon icon-calendar-week"></span> آینده</button>
                <button class="nav-btn-mobile" data-filter="past"><span class="icon icon-calendar-minus"></span> گذشته</button>
                <button class="nav-btn-mobile" data-filter="completed"><span class="icon icon-check-circle"></span> انجام شده</button>
                <button class="nav-btn-mobile" data-filter="all"><span class="icon icon-list"></span> همه</button>
            </div>
        </div>

        <div class="menu-section">
            <div class="menu-section-title"><span class="icon icon-tools"></span> مدیریت</div>
            <div class="menu-section-buttons">
                <a href="../projects/index.php"><span class="icon icon-project"></span> پروژه‌ها</a>
                <button onclick="openCategoryModal(); closeMobileMenu();"><span class="icon icon-tag"></span> دسته‌بندی</button>
                <button onclick="location.href='habits.php'; closeMobileMenu();"><span class="icon icon-fire"></span> عادت‌ها</button>
            </div>
        </div>

        <div class="menu-section">
            <div class="menu-section-title"><span class="icon icon-export"></span> خروجی</div>
            <div class="menu-section-buttons">
                <button onclick="exportCSV(); closeMobileMenu();"><span class="icon icon-file-csv"></span> خروجی CSV</button>
            </div>
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
                            <input type="date" id="filterDateFrom" class="date-range-input" placeholder="از تاریخ">
                            <span>تا</span>
                            <input type="date" id="filterDateTo" class="date-range-input" placeholder="تا تاریخ">
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

