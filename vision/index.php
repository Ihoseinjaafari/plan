<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویژن برد - تخته رویاها و اهداف</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="vision.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="vision-container">
        <!-- Header -->
        <header class="vision-header">
            <div class="header-content">
                <h1><i class="fas fa-eye"></i> ویژن برد من</h1>
                <p class="subtitle">تخته رویاها و اهداف - انگیزه‌ای برای تلاش روزانه</p>
            </div>
            <div class="header-actions">
                <button id="addItemBtn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> افزودن آیتم جدید
                </button>
                <button id="clearAllBtn" class="btn btn-danger">
                    <i class="fas fa-trash"></i> پاک کردن همه
                </button>
            </div>
        </header>

        <!-- Vision Board Canvas -->
        <div id="visionBoard" class="vision-board">
            <!-- Items will be loaded here dynamically -->
        </div>

        <!-- Add Item Modal -->
        <div id="addItemModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>افزودن آیتم جدید به ویژن برد</h2>
                    <button class="close-btn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addItemForm">
                        <div class="form-group">
                            <label for="itemType">نوع آیتم:</label>
                            <select id="itemType" name="type" required>
                                <option value="text">متن انگیزشی</option>
                                <option value="image">تصویر</option>
                                <option value="mixed">ترکیب متن و تصویر</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="itemContent">متن (اختیاری):</label>
                            <textarea id="itemContent" name="content" rows="3" placeholder="یک جمله انگیزشی یا هدف خود را بنویسید..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="itemImage">آپلود تصویر:</label>
                            <div class="file-upload">
                                <input type="file" id="itemImage" name="image" accept="image/*">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>فایل را اینجا بکشید یا کلیک کنید</p>
                                </div>
                            </div>
                            <div id="imagePreview" class="image-preview"></div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">افزودن به ویژن برد</button>
                            <button type="button" class="btn btn-secondary close-modal">انصراف</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Item Modal -->
        <div id="editItemModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>ویرایش آیتم</h2>
                    <button class="close-btn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="editItemForm">
                        <input type="hidden" id="editItemId" name="id">
                        <div class="form-group">
                            <label for="editContent">متن:</label>
                            <textarea id="editContent" name="content" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="editImage">تغییر تصویر:</label>
                            <div class="file-upload">
                                <input type="file" id="editImage" name="image" accept="image/*">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>فایل جدید را آپلود کنید (اختیاری)</p>
                                </div>
                            </div>
                            <div id="editImagePreview" class="image-preview"></div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                            <button type="button" class="btn btn-secondary close-modal">انصراف</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Context Menu -->
        <div id="contextMenu" class="context-menu">
            <ul>
                <li data-action="edit"><i class="fas fa-edit"></i> ویرایش</li>
                <li data-action="delete"><i class="fas fa-trash"></i> حذف</li>
                <li data-action="bring-front"><i class="fas fa-layer-group"></i> آوردن به جلو</li>
            </ul>
        </div>
    </div>

    <script src="vision.js"></script>
</body>
</html>
