<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویژن برد - تخته رویاها و اهداف</title>
    <!-- فونت وزیر -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <!-- آیکون‌ها -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* استایل‌های پایه و ریست */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow: hidden;
            direction: rtl;
        }

        /* کانتینر اصلی */
        .vision-container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* هدر */
        .vision-header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-content h1 {
            color: #667eea;
            font-size: 1.8rem;
            margin-bottom: 0.3rem;
        }

        .header-content .subtitle {
            color: #666;
            font-size: 0.9rem;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* دکمه‌ها */
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-danger {
            background: #ff4757;
            color: white;
        }

        .btn-danger:hover {
            background: #ff3838;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f1f2f6;
            color: #333;
        }

        /* بوم ویژن برد */
        .vision-board {
            flex: 1;
            position: relative;
            background: #f8f9fa;
            overflow: hidden;
            background-image: 
                radial-gradient(#ddd 1px, transparent 1px),
                radial-gradient(#ddd 1px, transparent 1px);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
        }

        /* آیتم‌ها روی بوم */
        .vision-item {
            position: absolute;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            cursor: move;
            touch-action: none;
            user-select: none;
            min-width: 150px;
            min-height: 150px;
            overflow: hidden;
            transition: box-shadow 0.3s ease;
        }

        .vision-item:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            z-index: 100 !important;
        }

        .vision-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            pointer-events: none;
        }

        .vision-item .item-content {
            padding: 1rem;
            font-size: 0.95rem;
            line-height: 1.6;
            color: #333;
            text-align: center;
        }

        .vision-item.has-image .item-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.9);
            border-top: 1px solid #eee;
            border-radius: 0 0 12px 12px;
        }

        /* دسته جابجایی */
        .drag-handle {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: rgba(102, 126, 234, 0.8);
            cursor: move;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 10;
        }

        .vision-item:hover .drag-handle {
            opacity: 1;
        }

        /* مودال‌ها */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            color: #667eea;
            font-size: 1.3rem;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #333;
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* فرم‌ها */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: bold;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        /* آپلود فایل */
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .file-upload:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .file-upload input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }

        .upload-placeholder i {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .upload-placeholder p {
            color: #999;
            font-size: 0.9rem;
        }

        .image-preview {
            margin-top: 1rem;
            max-height: 200px;
            overflow: hidden;
            border-radius: 8px;
        }

        .image-preview img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }

        .form-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        /* منوی زمینه */
        .context-menu {
            display: none;
            position: fixed;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 3000;
            min-width: 150px;
            overflow: hidden;
        }

        .context-menu.active {
            display: block;
        }

        .context-menu ul {
            list-style: none;
        }

        .context-menu li {
            padding: 0.8rem 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #333;
        }

        .context-menu li:hover {
            background: #f5f5f5;
        }

        .context-menu li i {
            width: 20px;
            text-align: center;
        }

        /* ریسپانسیو */
        @media (max-width: 768px) {
            .vision-header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions {
                justify-content: center;
            }

            .header-content h1 {
                font-size: 1.4rem;
            }
        }
    </style>
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

    <!-- کتابخانه Interact.js برای Drag & Drop حرفه‌ای -->
    <script src="https://cdn.jsdelivr.net/npm/interactjs@1.10.17/dist/interact.min.js"></script>
    <script src="vision.js"></script>
</body>
</html>
