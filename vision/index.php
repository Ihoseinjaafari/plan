<?php
// ==================== بررسی فعال بودن ماژول ====================
$settingsFile = __DIR__ . '/../data/settings.json';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (isset($settings['modules']['vision']['enabled']) && 
        $settings['modules']['vision']['enabled'] === false) {
        header('Location: ../disabled_module.php?module=vision');
        exit;
    }
}

session_start();

// احراز هویت: اگر کاربر لاگین نکرده، به صفحه ورود هدایت شود
if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
    header('Location: ../planner/login.php');
    exit;
}

$current_user = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویژن برد - تابلو آرزوها</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../planner/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
    <style>
        :root { --primary-color: #4f46e5; --bg-color: #f3f4f6; }
        body { font-family: 'Vazirmatn', sans-serif; background-color: var(--bg-color); margin: 0; padding: 0; overflow: hidden; }
        #vision-board-container { width: 100vw; height: calc(100vh - 80px); position: relative; overflow: auto; background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 20px 20px; background-color: #f8fafc; }
        .toolbar { position: fixed; top: 110px; left: 50%; transform: translateX(-50%); background: white; padding: 10px 20px; border-radius: 50px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); display: flex; gap: 15px; z-index: 1000; align-items: center; }
        .tool-btn { background: #f3f4f6; border: none; padding: 10px 15px; border-radius: 20px; cursor: pointer; font-family: 'Vazirmatn', sans-serif; font-weight: bold; color: #374151; transition: all 0.2s; display: flex; align-items: center; gap: 5px; }
        .tool-btn:hover { background: #e5e7eb; transform: translateY(-2px); }
        .tool-btn.primary { background: var(--primary-color); color: white; }
        .tool-btn.primary:hover { background: #4338ca; }
        .tool-btn.danger { background: #ef4444; color: white; }
        .vision-item { position: absolute; background: white; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); cursor: move; user-select: none; touch-action: none; min-width: 100px; min-height: 100px; display: flex; flex-direction: column; overflow: visible; border: 1px solid transparent; }
        .vision-item.selected { border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(79,70,229,0.3); }
        .vision-item img { width: 100%; height: 100%; object-fit: contain; pointer-events: none; border-radius: 8px; }
        .vision-item textarea { width: 100%; height: 100%; border: none; background: transparent; resize: none; padding: 10px; font-family: 'Vazirmatn', sans-serif; font-size: 14px; outline: none; text-align: center; }
        .delete-btn { position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; border: none; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; display: none; align-items: center; justify-content: center; font-size: 16px; z-index: 10; }
        .vision-item:hover .delete-btn { display: flex; }
        .resize-handle { position: absolute; bottom: -5px; right: -5px; width: 15px; height: 15px; background: var(--primary-color); border-radius: 50%; cursor: nwse-resize; display: none; }
        .vision-item:hover .resize-handle { display: block; }
        #file-input { display: none; }
        #status-msg { font-size: 0.9rem; color: #059669; margin-right: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="toolbar">
        <button class="tool-btn primary" onclick="triggerImageUpload()"><span>📷</span> افزودن تصویر</button>
        <button class="tool-btn" onclick="showTextOptions()"><span>📝</span> یادداشت</button>
        <div style="width: 1px; height: 20px; background: #ddd; margin: 0 5px;"></div>
        <button class="tool-btn primary" onclick="saveBoard()"><span>💾</span> ذخیره تغییرات</button>
        <button class="tool-btn danger" onclick="clearBoard()"><span>🗑️</span> پاک کردن همه</button>
        <span id="status-msg"></span>
    </div>
    
    <!-- مودال انتخاب نوع یادداشت -->
    <div id="textOptionsModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:25px; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.2); z-index:2000; min-width:300px;">
        <h3 style="margin:0 0 20px 0; font-family:'Vazirmatn',sans-serif; color:#374151;">نوع یادداشت را انتخاب کنید:</h3>
        <button class="tool-btn" style="width:100%; margin-bottom:10px; justify-content:center;" onclick="addTextNote('full')">📝 یادداشت کامل (متن + عنوان)</button>
        <button class="tool-btn" style="width:100%; margin-bottom:10px; justify-content:center;" onclick="addTextNote('title-only','small')">🏷️ فقط عنوان (کوچک)</button>
        <button class="tool-btn" style="width:100%; margin-bottom:10px; justify-content:center;" onclick="addTextNote('title-only','medium')">🏷️ فقط عنوان (متوسط)</button>
        <button class="tool-btn" style="width:100%; margin-bottom:10px; justify-content:center;" onclick="addTextNote('title-only','large')">🏷️ فقط عنوان (بزرگ)</button>
        <button class="tool-btn danger" style="width:100%; margin-top:15px; justify-content:center;" onclick="closeTextOptions()">انصراف</button>
    </div>
    
    <input type="file" id="file-input" accept="image/*" onchange="handleFileSelect(event)">
    <div id="vision-board-container"></div>
    <script>
        const boardContainer = document.getElementById('vision-board-container');
        const statusMsg = document.getElementById('status-msg');
        let items = [];
        let currentUserId = '<?php echo $current_user; ?>';

        document.addEventListener('DOMContentLoaded', () => { loadBoard(); setupInteract(); });

        function setupInteract() {
            interact('.vision-item').draggable({
                listeners: {
                    move (event) {
                        const target = event.target;
                        const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
                        const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;
                        target.style.transform = `translate(${x}px, ${y}px)`;
                        target.setAttribute('data-x', x);
                        target.setAttribute('data-y', y);
                    }
                },
                modifiers: [interact.modifiers.restrictRect({ restriction: 'parent', endOnly: true })]
            }).resizable({
                edges: { left: true, right: true, bottom: true, top: true },
                listeners: {
                    move: function (event) {
                        let { x, y } = event.target.dataset;
                        x = (parseFloat(x) || 0) + event.deltaRect.left;
                        y = (parseFloat(y) || 0) + event.deltaRect.top;
                        Object.assign(event.target.style, { width: `${event.rect.width}px`, height: `${event.rect.height}px`, transform: `translate(${x}px, ${y}px)` });
                        Object.assign(event.target.dataset, { x, y });
                    }
                },
                modifiers: [interact.modifiers.restrictSize({ min: { width: 100, height: 100 } }), interact.modifiers.restrictEdges({ outer: 'parent' })]
            }).on('tap', function (event) {
                document.querySelectorAll('.vision-item').forEach(el => el.classList.remove('selected'));
                event.currentTarget.classList.add('selected');
            });
        }

        function triggerImageUpload() { document.getElementById('file-input').click(); }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) { createImageItem(e.target.result); };
            reader.readAsDataURL(file);
            event.target.value = '';
        }

        function createImageItem(src, x = 50, y = 50, width = 300, height = 200) {
            const id = 'item-' + Date.now();
            const item = document.createElement('div');
            item.className = 'vision-item';
            item.id = id;
            item.style.width = width + 'px';
            item.style.height = height + 'px';
            item.style.transform = `translate(${x}px, ${y}px)`;
            item.setAttribute('data-x', x);
            item.setAttribute('data-y', y);
            item.dataset.type = 'image';
            item.dataset.src = src;
            item.innerHTML = `<img src="${src}" draggable="false"><button class="delete-btn" onclick="removeItem('${id}')">×</button><div class="resize-handle"></div>`;
            boardContainer.appendChild(item);
            items.push({ id, type: 'image', src, x, y, width, height });
        }

        function addTextNote(type = 'full', size = 'medium') {
            closeTextOptions();
            const id = 'item-' + Date.now();
            const x = 100 + Math.random() * 50;
            const y = 100 + Math.random() * 50;
            
            let width, height, content, placeholder, fontSize;
            
            if (type === 'title-only') {
                // فقط عنوان با سایزهای مختلف
                if (size === 'small') {
                    width = 150;
                    height = 50;
                    fontSize = '16px';
                } else if (size === 'medium') {
                    width = 200;
                    height = 70;
                    fontSize = '20px';
                } else if (size === 'large') {
                    width = 300;
                    height = 100;
                    fontSize = '28px';
                }
                content = 'عنوان جدید';
                placeholder = 'عنوان خود را بنویسید...';
            } else {
                // یادداشت کامل
                width = 250;
                height = 150;
                fontSize = '14px';
                content = 'یادداشت جدید...';
                placeholder = 'متن انگیزشی خود را بنویسید...';
            }
            
            const item = document.createElement('div');
            item.className = 'vision-item';
            item.id = id;
            item.style.width = width + 'px';
            item.style.height = height + 'px';
            item.style.transform = `translate(${x}px, ${y}px)`;
            item.setAttribute('data-x', x);
            item.setAttribute('data-y', y);
            item.dataset.type = 'text';
            item.dataset.textType = type;
            item.dataset.size = size;
            
            const textarea = document.createElement('textarea');
            textarea.placeholder = placeholder;
            textarea.value = content;
            textarea.style.fontSize = fontSize;
            if (type === 'title-only') {
                textarea.style.fontWeight = 'bold';
                textarea.style.textAlign = 'center';
                textarea.style.display = 'flex';
                textarea.style.alignItems = 'center';
                textarea.style.justifyContent = 'center';
            }
            textarea.addEventListener('input', (e) => { item.dataset.content = e.target.value; });
            
            item.innerHTML = `<button class="delete-btn" onclick="removeItem('${id}')">×</button><div class="resize-handle"></div>`;
            item.appendChild(textarea);
            
            boardContainer.appendChild(item);
            items.push({ id, type: 'text', textType: type, size: size, content: content, x, y, width, height, fontSize });
        }

        function showTextOptions() {
            document.getElementById('textOptionsModal').style.display = 'block';
        }

        function closeTextOptions() {
            document.getElementById('textOptionsModal').style.display = 'none';
        }

        function removeItem(id) { const el = document.getElementById(id); if (el) el.remove(); items = items.filter(i => i.id !== id); }

        function saveBoard() {
            showStatus('در حال ذخیره...');
            items = [];
            document.querySelectorAll('.vision-item').forEach(el => {
                const x = parseFloat(el.getAttribute('data-x')) || 0;
                const y = parseFloat(el.getAttribute('data-y')) || 0;
                const width = el.offsetWidth;
                const height = el.offsetHeight;
                const itemData = { id: el.id, type: el.dataset.type, x, y, width, height };
                if (el.dataset.type === 'image') { 
                    itemData.src = el.dataset.src; 
                } else if (el.dataset.type === 'text') { 
                    itemData.content = el.querySelector('textarea').value;
                    itemData.textType = el.dataset.textType || 'full';
                    itemData.size = el.dataset.size || 'medium';
                    const textarea = el.querySelector('textarea');
                    if (textarea && textarea.style.fontSize) {
                        itemData.fontSize = textarea.style.fontSize;
                    }
                }
                items.push(itemData);
            });
            fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'save', user: currentUserId, items: items }) })
            .then(res => res.json()).then(data => {
                if (data.success) { showStatus('ذخیره شد! ✅'); setTimeout(() => showStatus(''), 2000); }
                else { showStatus('خطا در ذخیره: ' + data.message); }
            }).catch(err => { console.error(err); showStatus('خطای ارتباط با سرور'); });
        }

        function loadBoard() {
            fetch(`api.php?action=load&user=${currentUserId}`).then(res => res.json()).then(data => {
                if (data.success && data.items) {
                    items = data.items;
                    boardContainer.innerHTML = '';
                    items.forEach(item => {
                        if (item.type === 'image') { 
                            createImageItem(item.src, item.x, item.y, item.width, item.height); 
                        } else if (item.type === 'text') {
                            const id = item.id;
                            const el = document.createElement('div');
                            el.className = 'vision-item';
                            el.id = id;
                            el.style.width = item.width + 'px';
                            el.style.height = item.height + 'px';
                            el.style.transform = `translate(${item.x}px, ${item.y}px)`;
                            el.setAttribute('data-x', item.x);
                            el.setAttribute('data-y', item.y);
                            el.dataset.type = 'text';
                            el.dataset.textType = item.textType || 'full';
                            el.dataset.size = item.size || 'medium';
                            
                            const textarea = document.createElement('textarea');
                            textarea.value = item.content || '';
                            if (item.fontSize) {
                                textarea.style.fontSize = item.fontSize;
                            }
                            if (item.textType === 'title-only') {
                                textarea.style.fontWeight = 'bold';
                                textarea.style.textAlign = 'center';
                                textarea.style.display = 'flex';
                                textarea.style.alignItems = 'center';
                                textarea.style.justifyContent = 'center';
                            }
                            textarea.addEventListener('input', (e) => { el.dataset.content = e.target.value; });
                            
                            el.innerHTML = `<button class="delete-btn" onclick="removeItem('${id}')">×</button><div class="resize-handle"></div>`;
                            el.appendChild(textarea);
                            
                            boardContainer.appendChild(el);
                        }
                    });
                    setupInteract();
                }
            }).catch(err => console.error('Error loading:', err));
        }

        function clearBoard() { 
            if(confirm('آیا مطمئن هستید؟ تمام آیتم‌ها پاک خواهند شد.')) { 
                boardContainer.innerHTML = ''; 
                items = []; 
                saveBoard(); 
            } 
        }
        
        function showStatus(msg) { statusMsg.textContent = msg; }
    </script>
</body>
</html>
