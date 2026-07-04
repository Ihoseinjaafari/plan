<!-- health/template.php -->

<div class="health-container">
    <!-- ===== هدر سلامت ===== -->
    <div class="health-header">
        <h1><span class="icon icon-heart"></span> سلامت زنان</h1>
        <p class="health-subtitle">ثبت و پیگیری چرخه قاعدگی، علائم و پیش‌بینی‌ها</p>
    </div>

    <!-- ===== وضعیت فعلی ===== -->
    <div class="current-status-card" id="currentStatusCard">
        <div class="status-phase" id="statusPhase">
            <span class="phase-icon" id="phaseIcon"></span>
            <span class="phase-text" id="phaseText">در حال بارگذاری...</span>
        </div>
        <div class="status-details" id="statusDetails">
            <div class="status-item">
                <span class="label">روز چرخه</span>
                <span class="value" id="cycleDay">-</span>
            </div>
            <div class="status-item">
                <span class="label">روزهای باقی‌مانده</span>
                <span class="value" id="daysRemaining">-</span>
            </div>
            <div class="status-item">
                <span class="label">تاریخ بعدی</span>
                <span class="value" id="nextPeriod">-</span>
            </div>
        </div>
    </div>

    <!-- ===== آمار سریع ===== -->
    <div class="quick-stats">
        <div class="stat-card">
            <div class="stat-number" id="statCycles">0</div>
            <div class="stat-label">سیکل‌های ثبت‌شده</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="statSymptoms">0</div>
            <div class="stat-label">علائم ثبت‌شده</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="statAvgCycle">-</div>
            <div class="stat-label">میانگین سیکل</div>
        </div>
    </div>

    <!-- ===== تب‌ها ===== -->
    <div class="health-tabs">
        <button class="tab-btn active" data-tab="dashboard"><span class="icon icon-home"></span> داشبورد</button>
        <button class="tab-btn" data-tab="cycles"><span class="icon icon-calendar"></span> سیکل‌ها</button>
        <button class="tab-btn" data-tab="symptoms"><span class="icon icon-heart-pulse"></span> علائم</button>
        <button class="tab-btn" data-tab="predict"><span class="icon icon-chart-line"></span> پیش‌بینی</button>
    </div>

    <!-- ===== محتوای تب‌ها ===== -->
    <div class="tab-content">
        <!-- ===== داشبورد ===== -->
        <div class="tab-panel active" id="panel-dashboard">
            <div class="dashboard-grid">
                <!-- تقویم خلاصه -->
                <div class="dashboard-card">
                    <div class="card-title"><span class="icon icon-calendar"></span> تقویم خلاصه</div>
                    <div id="miniCalendar"></div>
                </div>

                <!-- علائم اخیر -->
                <div class="dashboard-card">
                    <div class="card-title"><span class="icon icon-heart-pulse"></span> علائم اخیر</div>
                    <div id="recentSymptoms" class="symptoms-list">
                        <p style="color: var(--text-muted); text-align: center;">هنوز علائمی ثبت نشده است</p>
                    </div>
                </div>

                <!-- پیش‌بینی -->
                <div class="dashboard-card">
                    <div class="card-title"><span class="icon icon-chart-line"></span> پیش‌بینی</div>
                    <div id="predictionInfo">
                        <p style="color: var(--text-muted); text-align: center;">اطلاعات کافی برای پیش‌بینی وجود ندارد</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== سیکل‌ها ===== -->
        <div class="tab-panel" id="panel-cycles">
            <div class="panel-header">
                <h2><span class="icon icon-calendar"></span> تاریخچه سیکل‌ها</h2>
                <button class="btn-add" id="addCycleBtn"><span class="icon icon-plus"></span> ثبت سیکل جدید</button>
            </div>
            <div id="cyclesList" class="cycles-list">
                <p style="color: var(--text-muted); text-align: center; padding: 40px;">هیچ سیکلی ثبت نشده است</p>
            </div>
        </div>

        <!-- ===== علائم ===== -->
        <div class="tab-panel" id="panel-symptoms">
            <div class="panel-header">
                <h2><span class="icon icon-heart-pulse"></span> ثبت علائم</h2>
                <button class="btn-add" id="addSymptomBtn"><span class="icon icon-plus"></span> ثبت علامت جدید</button>
            </div>

            <!-- فیلتر علائم -->
            <div class="symptom-filters">
                <select id="symptomFilterType" class="filter-select">
                    <option value="">همه علائم</option>
                    <option value="pain">درد</option>
                    <option value="mood">خلق و خو</option>
                    <option value="physical">علائم جسمی</option>
                    <option value="digestive">گوارشی</option>
                    <option value="other">سایر</option>
                </select>
                <input type="date" id="symptomFilterDate" class="filter-date" placeholder="فیلتر تاریخ">
            </div>

            <div id="symptomsList" class="symptoms-list">
                <p style="color: var(--text-muted); text-align: center; padding: 40px;">هیچ علامتی ثبت نشده است</p>
            </div>
        </div>

        <!-- ===== پیش‌بینی ===== -->
        <div class="tab-panel" id="panel-predict">
            <div class="panel-header">
                <h2><span class="icon icon-chart-line"></span> پیش‌بینی‌ها</h2>
            </div>
            <div class="prediction-grid" id="predictionGrid">
                <div class="prediction-card">
                    <div class="prediction-label">میانگین طول سیکل</div>
                    <div class="prediction-value" id="predAvgCycle">-</div>
                </div>
                <div class="prediction-card">
                    <div class="prediction-label">تاریخ شروع بعدی</div>
                    <div class="prediction-value" id="predNextStart">-</div>
                </div>
                <div class="prediction-card">
                    <div class="prediction-label">تاریخ تخمک‌گذاری</div>
                    <div class="prediction-value" id="predOvulation">-</div>
                </div>
                <div class="prediction-card">
                    <div class="prediction-label">پنجره باروری</div>
                    <div class="prediction-value" id="predFertile">-</div>
                </div>
            </div>

            <div class="prediction-note">
                <span class="icon icon-info-circle"></span>
                برای پیش‌بینی دقیق‌تر، حداقل ۳ سیکل کامل ثبت کنید
            </div>
        </div>
    </div>
</div>

<!-- ===== مودال ثبت سیکل ===== -->
<div id="cycleModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header"><span class="icon icon-calendar"></span> ثبت سیکل جدید</div>
        <div class="modal-body">
            <input type="hidden" id="editCycleId">
            <label>تاریخ شروع</label>
            <input type="date" id="cycleStartDate" required>
            <label>تاریخ پایان (اختیاری)</label>
            <input type="date" id="cycleEndDate">
            <label>شدت خونریزی</label>
            <select id="cycleFlow">
                <option value="light">کم</option>
                <option value="medium" selected>متوسط</option>
                <option value="heavy">زیاد</option>
            </select>
            <label>یادداشت</label>
            <textarea id="cycleNotes" placeholder="یادداشت‌های اضافی..." rows="3"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeCycleModal()"><span class="icon icon-close"></span> انصراف</button>
            <button class="btn-save" id="saveCycleBtn"><span class="icon icon-save"></span> ذخیره</button>
        </div>
    </div>
</div>

<!-- ===== مودال ثبت علامت ===== -->
<div id="symptomModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header"><span class="icon icon-heart-pulse"></span> ثبت علامت جدید</div>
        <div class="modal-body">
            <input type="hidden" id="editSymptomId">
            <label>تاریخ</label>
            <input type="date" id="symptomDate" required>
            <label>نوع علامت</label>
            <select id="symptomType">
                <option value="pain">درد (کمر، سینه، شکم)</option>
                <option value="mood">خلق و خو (نوسان، افسردگی)</option>
                <option value="physical">علائم جسمی (نفخ، خستگی)</option>
                <option value="digestive">گوارشی (تهوع، یبوست)</option>
                <option value="other">سایر</option>
            </select>
            <label>شدت</label>
            <select id="symptomSeverity">
                <option value="low">کم</option>
                <option value="medium" selected>متوسط</option>
                <option value="high">زیاد</option>
            </select>
            <label>یادداشت</label>
            <textarea id="symptomNotes" placeholder="توضیحات بیشتر..." rows="3"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeSymptomModal()"><span class="icon icon-close"></span> انصراف</button>
            <button class="btn-save" id="saveSymptomBtn"><span class="icon icon-save"></span> ذخیره</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>
