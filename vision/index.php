<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویژن برد | Vision Board</title>
    <!-- فونت وزیر -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <!-- کتابخانه Interact.js برای جابجایی و تغییر اندازه -->
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: #f0f2f5;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* نوار ابزار */
        #toolbar {
            height: 60px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 0 20px;
            z-index: 1000;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary { background: #4f46e5; color: white; }
        .btn-primary:hover { background: #4338ca; }
        
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }

        /* بوم */
        #vision-board {
            flex: 1;
            position: relative;
            overflow: auto;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 25px 25px;
        }

        /* آیتم‌ها */
        .vision-item {
            position: absolute;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            touch-action: none;
            user-select: none;
            min-width: 120px;
            min-height: 120px;
            border: 2px solid transparent;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .vision-item:hover, .vision-item.dragging {
            border-color: #4f46e5;
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            z-index: 999 !important;
        }

        .vision-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            pointer-events: none;
            display: block;
            border-radius: 6px;
        }

        .vision-item textarea {
            width: 100%;
            height: 100%;
            border: none;
            resize: none;
            padding: 12px;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 14px;
            background: transparent;
            outline: none;
        }

        /* دستگیره‌ها */
        .resize-handle {
            position: absolute;
            bottom: -6px;
            right: -6px;
            width: 24px;
            height: 24px;
            background: #4f46e5;
            border-radius: 50%;
            cursor: nwse-resize;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 1000;
        }

        .vision-item:hover .resize-handle,
        .vision-item.dragging .resize-handle {
            opacity: 1;
        }

        /* دکمه حذف */
        .delete-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 26px;
            height: 26px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: bold;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .vision-item:hover .delete-btn,
        .vision-item.dragging .delete-btn {
            opacity: 1;
        }

        /* هدر برای دراگ کردن نوت‌ها */
        .note-header {
            height: 28px;
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            cursor: grab;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 12px;
            border-radius: 6px 6px 0 0;
        }

        /* لودینگ */
        #loading {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 12px 24px;
            border-radius: 24px;
            display: none;
            z-index: 2000;
        }

        /*Toast*/
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            z-index: 3000;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Input file مخفی */
        #file-input { display: none; }
    </style>
</head>
<body>
    <!-- نوار ابزار -->
    <div id="toolbar">
        <button class="btn btn-primary" onclick="triggerFileInput()">
            📷 افزودن تصویر
        </button>
        <button class="btn btn-primary" onclick="addNote()">
            📝 افزودن یادداشت
        </button>
        <div style="flex:1;"></div>
        <button class="btn btn-success" onclick="saveBoard()">
            💾 ذخیره
        </button>
        <button class="btn btn-danger" onclick="clearBoard()">
            🗑️ پاکسازی
        </button>
    </div>

    <!-- لودینگ -->
    <div id="loading">در حال پردازش...</div>

    <!-- بوم -->
    <div id="vision-board"></div>

    <!-- اینپوت فایل -->
    <input type="file" id="file-input" accept="image/*" onchange="handleFileSelect(this)">

    <script>
        let items = [];
        let board = document.getElementById('vision-board');
        let zIndex = 100;

        // بارگذاری اولیه
        window.addEventListener('DOMContentLoaded', () => {
            loadBoard();
            setupInteract();
        });

        // تنظیم Interact.js
        function setupInteract() {
            interact('.vision-item')
                .draggable({
                    listeners: {
                        move(event) {
                            const target = event.target;
                            let x = parseFloat(target.dataset.x || 0) + event.dx;
                            let y = parseFloat(target.dataset.y || 0) + event.dy;
                            
                            target.style.transform = `translate(${x}px, ${y}px)`;
                            target.dataset.x = x;
                            target.dataset.y = y;
                            target.classList.add('dragging');
                        },
                        end(event) {
                            event.target.classList.remove('dragging');
                            updateItemData(event.target);
                            debouncedSave();
                        }
                    },
                    modifiers: [
                        interact.modifiers.restrictRect({
                            restriction: 'parent',
                            endOnly: true
                        })
                    ]
                })
                .resizable({
                    edges: { right: true, bottom: true },
                    listeners: {
                        move(event) {
                            let x = parseFloat(event.target.dataset.x || 0);
                            let y = parseFloat(event.target.dataset.y || 0);
                            
                            event.target.style.width = event.rect.width + 'px';
                            event.target.style.height = event.rect.height + 'px';
                            event.target.style.transform = `translate(${x}px, ${y}px)`;
                            
                            event.target.dataset.x = x;
                            event.target.dataset.y = y;
                            event.target.classList.add('dragging');
                        },
                        end(event) {
                            event.target.classList.remove('dragging');
                            updateItemData(event.target);
                            debouncedSave();
                        }
                    },
                    modifiers: [
                        interact.modifiers.restrictSize({
                            min: { width: 100, height: 100 }
                        })
                    ],
                    inertia: true
                })
                .on('tap', function(event) {
                    zIndex++;
                    event.currentTarget.style.zIndex = zIndex;
                });
        }

        // افزودن تصویر
        function triggerFileInput() {
            document.getElementById('file-input').click();
        }

        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const formData = new FormData();
                formData.append('image', file);
                formData.append('action', 'upload');
                
                showLoading(true);
                
                fetch('api.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        showLoading(false);
                        if (data.success) {
                            createItem('image', data.path, 50, 50, 300, 300);
                            saveBoard();
                        } else {
                            alert('خطا: ' + (data.error || 'نامشخص'));
                        }
                    })
                    .catch(err => {
                        showLoading(false);
                        alert('خطا در ارتباط با سرور');
                        console.error(err);
                    });
                
                input.value = '';
            }
        }

        // افزودن یادداشت
        function addNote() {
            createItem('note', 'یادداشت جدید...', 50, 50, 250, 200);
            saveBoard();
        }

        // ساخت آیتم
        function createItem(type, content, x, y, w, h) {
            const id = 'item-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            
            const div = document.createElement('div');
            div.className = 'vision-item';
            div.id = id;
            div.dataset.type = type;
            div.dataset.x = x;
            div.dataset.y = y;
            div.style.width = w + 'px';
            div.style.height = h + 'px';
            div.style.zIndex = ++zIndex;
            div.style.transform = `translate(${x}px, ${y}px)`;
            
            let html = `<div class="delete-btn" onclick="removeItem('${id}')">×</div>`;
            html += `<div class="resize-handle"></div>`;
            
            if (type === 'image') {
                html += `<img src="${content}" draggable="false">`;
            } else {
                html += `<div class="note-header">جابجایی</div>`;
                html += `<textarea>${content}</textarea>`;
            }
            
            div.innerHTML = html;
            board.appendChild(div);
            
            items.push({ id, type, content, x, y, w, h, z: zIndex });
        }

        // حذف آیتم
        function removeItem(id) {
            if (!confirm('حذف شود؟')) return;
            const el = document.getElementById(id);
            if (el) el.remove();
            items = items.filter(i => i.id !== id);
            saveBoard();
        }

        // پاک کردن همه
        function clearBoard() {
            if (!confirm('همه موارد پاک شوند؟')) return;
            board.innerHTML = '';
            items = [];
            saveBoard();
        }

        // بروزرسانی داده‌های آیتم
        function updateItemData(el) {
            const item = items.find(i => i.id === el.id);
            if (!item) return;
            
            item.x = parseFloat(el.dataset.x || 0);
            item.y = parseFloat(el.dataset.y || 0);
            item.w = parseFloat(el.style.width) || item.w;
            item.h = parseFloat(el.style.height) || item.h;
            item.z = parseInt(el.style.zIndex) || item.z;
            
            if (item.type === 'note') {
                const txt = el.querySelector('textarea');
                if (txt) item.content = txt.value;
            }
        }

        // ذخیره
        function saveBoard() {
            // بروزرسانی همه نوت‌ها
            document.querySelectorAll('.vision-item[data-type="note"]').forEach(el => updateItemData(el));
            
            showLoading(true);
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save', items })
            })
            .then(res => res.json())
            .then(data => {
                showLoading(false);
                if (data.success) showToast('ذخیره شد!');
                else alert('خطا در ذخیره‌سازی');
            })
            .catch(() => {
                showLoading(false);
                alert('خطا در ارتباط');
            });
        }

        // ذخیره با تاخیر
        let saveTimer;
        function debouncedSave() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(saveBoard, 800);
        }

        // بارگذاری
        function loadBoard() {
            showLoading(true);
            fetch('api.php?action=load')
                .then(res => res.json())
                .then(data => {
                    showLoading(false);
                    if (data.success && data.items) {
                        items = data.items;
                        board.innerHTML = '';
                        items.forEach(it => createItem(it.type, it.content, it.x, it.y, it.w, it.h));
                    }
                })
                .catch(() => showLoading(false));
        }

        // نمایش لودینگ
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }

        // Toast
        function showToast(msg) {
            const t = document.createElement('div');
            t.className = 'toast';
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 2500);
        }
    </script>
</body>
</html>
