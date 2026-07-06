let items = [];
let itemIdCounter = 0;

// بارگذاری اولیه
document.addEventListener('DOMContentLoaded', () => {
    loadBoard();
    setupInteract();
    setupEventListeners();
});

// تنظیم رویدادهای دکمه‌ها و مودال‌ها
function setupEventListeners() {
    const addItemBtn = document.getElementById('addItemBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    const addItemModal = document.getElementById('addItemModal');
    const editItemModal = document.getElementById('editItemModal');
    const closeButtons = document.querySelectorAll('.close-btn, .close-modal');
    const addItemForm = document.getElementById('addItemForm');
    const editItemForm = document.getElementById('editItemForm');
    const contextMenu = document.getElementById('contextMenu');

    // باز کردن مودال افزودن
    if (addItemBtn) {
        addItemBtn.addEventListener('click', () => {
            addItemModal.classList.add('active');
        });
    }

    // پاک کردن همه
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', clearBoard);
    }

    // بستن مودال‌ها
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').classList.remove('active');
        });
    });

    // بستن مودال با کلیک بیرون
    window.addEventListener('click', (e) => {
        if (e.target === addItemModal) addItemModal.classList.remove('active');
        if (e.target === editItemModal) editItemModal.classList.remove('active');
        if (e.target !== contextMenu) contextMenu.classList.remove('active');
    });

    // ارسال فرم افزودن
    if (addItemForm) {
        addItemForm.addEventListener('submit', handleAddItem);
    }

    // ارسال فرم ویرایش
    if (editItemForm) {
        editItemForm.addEventListener('submit', handleEditItem);
    }

    // پیش‌نمایش تصویر در فرم افزودن
    const itemImageInput = document.getElementById('itemImage');
    const imagePreview = document.getElementById('imagePreview');
    if (itemImageInput && imagePreview) {
        itemImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.innerHTML = `<img src="${e.target.result}" alt="preview">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // پیش‌نمایش تصویر در فرم ویرایش
    const editImageInput = document.getElementById('editImage');
    const editImagePreview = document.getElementById('editImagePreview');
    if (editImageInput && editImagePreview) {
        editImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    editImagePreview.innerHTML = `<img src="${e.target.result}" alt="preview">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // منوی زمینه (کلیک راست)
    const board = document.getElementById('visionBoard');
    if (board) {
        board.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            const item = e.target.closest('.vision-item');
            if (item) {
                contextMenu.style.left = e.pageX + 'px';
                contextMenu.style.top = e.pageY + 'px';
                contextMenu.classList.add('active');
                contextMenu.dataset.itemId = item.id;
            }
        });
    }

    // کلیک روی آیتم‌های منوی زمینه
    if (contextMenu) {
        contextMenu.addEventListener('click', function(e) {
            const action = e.target.closest('li')?.dataset.action;
            const itemId = contextMenu.dataset.itemId;
            if (action && itemId) {
                handleContextAction(action, itemId);
                contextMenu.classList.remove('active');
            }
        });
    }
}

// مدیریت عملیات منوی زمینه
function handleContextAction(action, itemId) {
    const id = parseInt(itemId.replace('item-', ''));
    const item = items.find(i => i.id === id);
    
    if (!item) return;

    switch(action) {
        case 'edit':
            openEditModal(item);
            break;
        case 'delete':
            deleteItem(id);
            break;
        case 'bring-front':
            bringToFront(id);
            break;
    }
}

// باز کردن مودال ویرایش
function openEditModal(item) {
    const editItemModal = document.getElementById('editItemModal');
    const editItemId = document.getElementById('editItemId');
    const editContent = document.getElementById('editContent');
    const editImagePreview = document.getElementById('editImagePreview');

    if (editItemId) editItemId.value = item.id;
    if (editContent) editContent.value = item.content || '';
    
    if (editImagePreview && item.src) {
        editImagePreview.innerHTML = `<img src="${item.src}" alt="current">`;
    } else if (editImagePreview) {
        editImagePreview.innerHTML = '';
    }

    editItemModal.classList.add('active');
}

// مدیریت افزودن آیتم جدید
function handleAddItem(e) {
    e.preventDefault();
    const form = e.target;
    const type = document.getElementById('itemType').value;
    const content = document.getElementById('itemContent').value;
    const imageFile = document.getElementById('itemImage').files[0];

    if (type === 'image' || type === 'mixed') {
        if (!imageFile) {
            alert('لطفاً یک تصویر انتخاب کنید');
            return;
        }

        const formData = new FormData();
        formData.append('image', imageFile);
        formData.append('action', 'upload_image');

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const newItem = {
                    id: Date.now(),
                    type: type === 'image' ? 'image' : 'mixed',
                    src: data.path,
                    content: content,
                    x: Math.random() * 200 + 50,
                    y: Math.random() * 200 + 100,
                    width: 250,
                    height: 200
                };
                items.push(newItem);
                renderItems();
                saveBoard();
                document.getElementById('addItemModal').classList.remove('active');
                form.reset();
                document.getElementById('imagePreview').innerHTML = '';
            } else {
                alert('خطا در آپلود: ' + data.error);
            }
        })
        .catch(err => {
            alert('خطا در ارتباط با سرور');
            console.error(err);
        });
    } else {
        // فقط متن
        const newItem = {
            id: Date.now(),
            type: 'text',
            content: content,
            x: Math.random() * 200 + 50,
            y: Math.random() * 200 + 100,
            width: 200,
            height: 150
        };
        items.push(newItem);
        renderItems();
        saveBoard();
        document.getElementById('addItemModal').classList.remove('active');
        form.reset();
    }
}

// مدیریت ویرایش آیتم
function handleEditItem(e) {
    e.preventDefault();
    const form = e.target;
    const id = parseInt(document.getElementById('editItemId').value);
    const content = document.getElementById('editContent').value;
    const imageFile = document.getElementById('editImage').files[0];

    const item = items.find(i => i.id === id);
    if (!item) return;

    item.content = content;

    if (imageFile) {
        const formData = new FormData();
        formData.append('image', imageFile);
        formData.append('action', 'upload_image');

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                item.src = data.path;
                renderItems();
                saveBoard();
                document.getElementById('editItemModal').classList.remove('active');
                form.reset();
                document.getElementById('editImagePreview').innerHTML = '';
            } else {
                alert('خطا در آپلود: ' + data.error);
            }
        })
        .catch(err => {
            alert('خطا در ارتباط با سرور');
            console.error(err);
        });
    } else {
        renderItems();
        saveBoard();
        document.getElementById('editItemModal').classList.remove('active');
    }
}

// آوردن به جلو
function bringToFront(id) {
    const item = items.find(i => i.id === id);
    if (item) {
        item.zIndex = Date.now();
        renderItems();
        saveBoard();
    }
}

// تنظیمات Interact.js برای جابجایی و تغییر اندازه
function setupInteract() {
    interact('.vision-item')
        .draggable({
            allowFrom: '.drag-handle, .vision-item',
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
            edges: { left: true, right: true, bottom: true, top: true },
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

// ایجاد آیتم جدید در بوم
function addItem(item) {
    items.push(item);
    renderItems();
}

// رندر کردن تمام آیتم‌ها
function renderItems() {
    const board = document.getElementById('visionBoard');
    if (!board) return;
    
    board.innerHTML = '';

    items.forEach(item => {
        const el = document.createElement('div');
        el.className = 'vision-item';
        el.id = `item-${item.id}`;
        el.style.left = '0px';
        el.style.top = '0px';
        el.style.width = (item.width || 200) + 'px';
        el.style.height = (item.height || 150) + 'px';
        el.setAttribute('data-x', item.x || 0);
        el.setAttribute('data-y', item.y || 0);
        el.style.transform = `translate(${item.x || 0}px, ${item.y || 0}px)`;
        el.style.zIndex = item.zIndex || 1;

        // دسته جابجایی
        const dragHandle = document.createElement('div');
        dragHandle.className = 'drag-handle';
        dragHandle.innerHTML = '<i class="fas fa-arrows-alt"></i> جابجایی';
        el.appendChild(dragHandle);

        if (item.type === 'image' || item.type === 'mixed') {
            if (item.src) {
                const img = document.createElement('img');
                img.src = item.src;
                img.draggable = false;
                el.appendChild(img);
            }
        }

        if (item.content) {
            const contentDiv = document.createElement('div');
            contentDiv.className = 'item-content';
            contentDiv.textContent = item.content;
            el.appendChild(contentDiv);
            el.classList.add('has-image');
        }

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
    // ایجاد یا دریافت المان وضعیت
    let statusEl = document.getElementById('statusMsg');
    if (!statusEl) {
        statusEl = document.createElement('div');
        statusEl.id = 'statusMsg';
        statusEl.style.cssText = 'position:fixed;bottom:20px;left:20px;background:#333;color:white;padding:10px 20px;border-radius:8px;z-index:9999;font-family:Vazirmatn,sans-serif;';
        document.body.appendChild(statusEl);
    }
    statusEl.textContent = msg;
    setTimeout(() => {
        statusEl.textContent = 'آماده';
    }, 3000);
}
