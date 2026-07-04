
            <button class="add-task-fab" id="openAddTaskBtn">
                <span class="icon icon-plus"></span>
            </button>
        </div>
    </div>

    <!-- ===== مودال افزودن کار ===== -->
    <div id="addTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-plus-circle"></span> افزودن کار جدید</div>
            <div class="modal-body">
                <input type="text" id="addTitle" placeholder="عنوان کار..." required>
                <select id="addCategory"></select>
                <select id="addProject"><option value="">بدون پروژه</option></select>

                <!-- Picker تاریخ شمسی -->
                <div class="date-picker-wrapper">
                    <input type="text" id="addDate" class="date-picker-input" placeholder="تاریخ" value="<?php echo $todayJalali; ?>" readonly>
                    <div class="jalali-calendar" id="addDateCalendar"></div>
                </div>

                <!-- انتخاب زمان با اسلایدر -->
                <div class="time-selector-group">
                    <label><span class="icon icon-clock"></span> زمان:</label>
                    <div class="time-selector-wrapper">
                        <input type="range" class="time-selector" id="addTimeRange" min="0" max="1439" value="720">
                        <span class="time-display" id="addTimeDisplay">12:00</span>
                    </div>
                </div>

                <select id="addPriority">
                    <option value="high">🔴 اولویت بالا</option>
                    <option value="medium" selected>🟡 اولویت متوسط</option>
                    <option value="low">🟢 اولویت پایین</option>
                </select>
                <textarea id="addDescription" placeholder="توضیحات (اختیاری)..." rows="3"></textarea>

                <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 10px; border: 1px solid var(--border-color);">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);"><span class="icon icon-sitemap"></span> زیرتسک برای کار مادر</label>
                    <select id="addParentTask">
                        <option value="">بدون والد (تسک اصلی)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddTaskModal()"><span class="icon icon-close"></span> انصراف</button>
                <button class="btn-save" id="saveAddTaskBtn"><span class="icon icon-save"></span> افزودن کار</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال ویرایش کار ===== -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-edit"></span> ویرایش کار</div>
            <div class="modal-body">
                <input type="text" id="editTitle" placeholder="عنوان کار">
                <select id="editCategory"></select>
                <select id="editProject"><option value="">بدون پروژه</option></select>

                <!-- Picker تاریخ شمسی برای ویرایش -->
                <div class="date-picker-wrapper">
                    <input type="text" id="editDate" class="date-picker-input" placeholder="تاریخ" readonly>
                    <div class="jalali-calendar" id="editDateCalendar"></div>
                </div>

                <!-- انتخاب زمان با اسلایدر برای ویرایش -->
                <div class="time-selector-group">
                    <label><span class="icon icon-clock"></span> زمان:</label>
                    <div class="time-selector-wrapper">
                        <input type="range" class="time-selector" id="editTimeRange" min="0" max="1439" value="720">
                        <span class="time-display" id="editTimeDisplay">12:00</span>
                    </div>
                </div>

                <select id="editPriority">
                    <option value="high">🔴 بالا</option>
                    <option value="medium">🟡 متوسط</option>
                    <option value="low">🟢 پایین</option>
                </select>
                <textarea id="editDescription" placeholder="توضیحات..." rows="4"></textarea>

                <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 10px; border: 1px solid var(--border-color);">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);"><span class="icon icon-sitemap"></span> زیرتسک برای کار مادر</label>
                    <select id="editParentTask">
                        <option value="">بدون والد (تسک اصلی)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeEditModal()"><span class="icon icon-close"></span> انصراف</button>
                <button class="btn-save" onclick="saveEdit()"><span class="icon icon-save"></span> ذخیره تغییرات</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال پروژه‌ها ===== -->
    <div id="projectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-project"></span> مدیریت پروژه‌ها</div>
            <div class="modal-body">
                <div id="projectList"></div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <input type="text" id="newProjectName" placeholder="نام پروژه جدید" style="flex:1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <button class="btn-save" id="addProjectBtn" style="flex:0;"><span class="icon icon-plus"></span> افزودن</button>
                </div>
                <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 10px; font-size: 12px; color: var(--text-light); border: 1px solid var(--border-color);">
                    <span class="icon icon-info-circle"></span> برای مشاهده صفحه اختصاصی هر پروژه، روی نام آن کلیک کنید
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProjectModal()"><span class="icon icon-close"></span> بستن</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال صفحه پروژه ===== -->
    <div id="projectPageModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header"><span class="icon icon-project"></span> <span id="projectPageTitle"></span></div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">توضیحات پروژه:</label>
                    <div id="projectPageDesc" style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; min-height: 80px; white-space: pre-wrap; border: 1px solid var(--border-color); color: var(--text-secondary);"></div>
                    <button id="editProjectDescBtn" class="manage-btn" style="margin-top: 10px; background: rgba(102,126,234,0.2); color:#667eea; border: 1px solid rgba(102,126,234,0.15);"><span class="icon icon-edit"></span> ویرایش توضیحات</button>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">آمار پروژه:</label>
                    <div id="projectPageStats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px;"></div>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);">تسک‌های پروژه:</label>
                    <div id="projectPageTasks" style="max-height: 350px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProjectPageModal()"><span class="icon icon-close"></span> بستن</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال دسته‌ها ===== -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-tag"></span> مدیریت دسته‌بندی</div>
            <div class="modal-body">
                <div id="categoryList"></div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <input type="text" id="newCategoryName" placeholder="نام دسته جدید" style="flex:1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <button class="btn-save" id="addCategoryBtn" style="flex:0;"><span class="icon icon-plus"></span> افزودن</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCategoryModal()"><span class="icon icon-close"></span> بستن</button>
            </div>
        </div>
    </div>

    <!-- ===== مودال پروفایل ===== -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span class="icon icon-user"></span> پروفایل کاربری</div>
            <div class="modal-body">
                <div class="profile-info">
                    <div class="profile-avatar" style="background: <?php echo $currentUser['avatar_color'] ?? '#667eea'; ?>">
                        <?php echo mb_substr($currentUser['name'], 0, 1); ?>
                    </div>
                    <h3 id="profileName" style="color: var(--text-primary);"><?php echo htmlspecialchars($currentUser['name']); ?></h3>
                    <div class="profile-email" id="profileEmail"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                </div>

                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: var(--text-secondary);"><span class="icon icon-key"></span> تغییر رمز عبور</label>
                    <input type="password" id="newPassword" placeholder="رمز عبور جدید" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 15px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <input type="password" id="confirmNewPassword" placeholder="تکرار رمز عبور جدید" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 15px; background: rgba(255,255,255,0.03); color: var(--text-primary);">
                    <div id="passwordError" style="color: #dc3545; font-size: 12px; margin-bottom: 10px; display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeProfileModal()"><span class="icon icon-close"></span> انصراف</button>
                <button class="btn-save" id="changePasswordBtn"><span class="icon icon-save"></span> تغییر رمز عبور</button>
            </div>
        </div>
    </div>

