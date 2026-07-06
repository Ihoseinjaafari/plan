let items = [];
let itemIdCounter = 0;

// بارگذاری اولیه
document.addEventListener('DOMContentLoaded', () => {
    loadBoard();
    setupInteract();
});

// تنظیمات Interact.js برای جابجایی و تغییر اندازه
function setupInteract() {
    interact('.vision-item')
        .draggable({
            allowFrom: '.vision-item',
            listeners: {
                start (event) {
                    event.target.style.zIndex = 1000;
                },
                move (event) {
                    const target = event.target;
                    const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
                    const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;

                    target.style.transform = `translate(${x}px, ${y}px)`;
                    
                    target.setAttribute('data-x', x);
                    target.setAttribute('data-y', y);
                },
                end (event) {
                    const target = event.target;
                    target.style.zIndex = '';
                    saveItemPosition(target);
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
            edges: { bottom: true, right: true },
            listeners: {
                move (event) {
                    let { x, y } = event.target.dataset;
                    x = (parseFloat(x) || 0) + event.deltaRect.left;
                    y = (parseFloat(y) || 0) + event.deltaRect.top;

                    Object.assign(event.target.style, {
                        width: `${event.rect.width}px`,
                        height: `${event.rect.height}px`,
                        transform: `translate(${x}px, ${y}px)`
                    });

                    Object.assign(event.target.dataset, { x, y });
                },
                end (event) {
                    saveItemPosition(event.target);
                }
            },
            modifiers: [
                interact.modifiers.restrictSize({
                    min: { width: 100, height: 100 }
                }),
                interact.modifiers.restrictEdges({
                    outer: 'parent'
                })
            ]
        });
}

// آپلود تصویر
function uploadImage(input) {
    const file = input.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('image', file);
    formData.append('action', 'upload_image');

    showStatus('در حال آپلود...');

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            addItem({
                id: Date.now(),
                type: 'image',
                src: data.path,
                x: 50,
                y: 100,
                width: 200,
                height: 150
            });
            showStatus('تصویر اضافه شد');
            saveBoard();
        } else {
            showStatus('خطا: ' + data.error);
        }
    })
    .catch(err => {
        showStatus('خطا در ارتباط با سرور');
        console.error(err);
    });

    input.value = '';
}

// افزودن یادداشت متنی
function addTextNote() {
    const text = prompt('متن انگیزشی خود را وارد کنید:');
    if (text) {
        addItem({
            id: Date.now(),
            type: 'text',
            content: text,
            x: 100,
            y: 150,
            width: 200,
            height: 150
        });
        saveBoard();
    }
}

// ایجاد آیتم جدید در بوم
function addItem(item) {
    items.push(item);
    renderItems();
}

// رندر کردن تمام آیتم‌ها
function renderItems() {
    const board = document.getElementById('vision-board');
    board.innerHTML = '';

    items.forEach(item => {
        const el = document.createElement('div');
        el.className = 'vision-item';
        el.id = `item-${item.id}`;
        el.style.left = '0px';
        el.style.top = '0px';
        el.style.width = item.width + 'px';
        el.style.height = item.height + 'px';
        el.setAttribute('data-x', item.x);
        el.setAttribute('data-y', item.y);
        el.style.transform = `translate(${item.x}px, ${item.y}px)`;

        if (item.type === 'image') {
            const img = document.createElement('img');
            img.src = item.src;
            img.draggable = false;
            el.appendChild(img);
        } else if (item.type === 'text') {
            const textarea = document.createElement('textarea');
            textarea.value = item.content;
            textarea.onchange = () => {
                item.content = textarea.value;
                saveBoard();
            };
            el.appendChild(textarea);
        }

        // دکمه حذف
        const deleteBtn = document.createElement('div');
        deleteBtn.className = 'resize-handle';
        deleteBtn.style.background = '#ef4444';
        deleteBtn.style.bottom = 'auto';
        deleteBtn.style.top = '-5px';
        deleteBtn.style.right = 'auto';
        deleteBtn.style.left = '-5px';
        deleteBtn.style.cursor = 'pointer';
        deleteBtn.innerHTML = '×';
        deleteBtn.onclick = (e) => {
            e.stopPropagation();
            deleteItem(item.id);
        };
        el.appendChild(deleteBtn);

        // دستگیره تغییر اندازه
        const resizeHandle = document.createElement('div');
        resizeHandle.className = 'resize-handle';
        el.appendChild(resizeHandle);

        board.appendChild(el);
    });

    // دوباره تنظیم Interact برای المان‌های جدید
    setupInteract();
}

// حذف آیتم
function deleteItem(id) {
    if (confirm('آیا مطمئن هستید؟')) {
        items = items.filter(item => item.id !== id);
        renderItems();
        saveBoard();
    }
}

// ذخیره موقعیت یک آیتم
function saveItemPosition(element) {
    const id = parseInt(element.id.replace('item-', ''));
    const item = items.find(i => i.id === id);
    
    if (item) {
        item.x = parseFloat(element.getAttribute('data-x')) || 0;
        item.y = parseFloat(element.getAttribute('data-y')) || 0;
        item.width = parseFloat(element.style.width) || item.width;
        item.height = parseFloat(element.style.height) || item.height;
        
        // ذخیره خودکار پس از هر جابجایی
        saveBoard();
    }
}

// ذخیره کل بوم
function saveBoard() {
    showStatus('در حال ذخیره...');

    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'save_board',
            items: items
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showStatus('ذخیره شد ✓');
        } else {
            showStatus('خطا در ذخیره: ' + data.error);
        }
    })
    .catch(err => {
        showStatus('خطا در ارتباط با سرور');
        console.error(err);
    });
}

// بارگذاری بوم از سرور
function loadBoard() {
    fetch('api.php?action=load_board')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.items) {
            items = data.items;
            itemIdCounter = Math.max(...items.map(i => i.id), 0) + 1;
            renderItems();
            showStatus('بارگذاری شد');
        }
    })
    .catch(err => {
        console.error('Error loading board:', err);
    });
}

// پاک کردن بوم
function clearBoard() {
    if (confirm('آیا مطمئن هستید که می‌خواهید همه چیز را پاک کنید؟')) {
        items = [];
        renderItems();
        saveBoard();
    }
}

// نمایش وضعیت
function showStatus(msg) {
    document.getElementById('statusMsg').textContent = msg;
    setTimeout(() => {
        document.getElementById('statusMsg').textContent = 'آماده';
    }, 3000);
}
