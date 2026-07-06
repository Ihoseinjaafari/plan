// متغیرهای سراسری
let items = [];
const board = document.getElementById('vision-board');
const imageInput = document.getElementById('imageInput');

// بارگذاری آیتم‌ها هنگام شروع
document.addEventListener('DOMContentLoaded', () => {
    loadItems();
    setupInteract();
});

// راه‌اندازی Interact.js برای جابجایی
function setupInteract() {
    interact('.vision-item')
        .draggable({
            allowFrom: '.drag-handle',
            listeners: {
                start (event) {
                    event.target.classList.add('dragging');
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
                    event.target.classList.remove('dragging');
                    const id = event.target.getAttribute('data-id');
                    saveItemPosition(id);
                }
            },
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: 'parent',
                    endOnly: true
                })
            ]
        })
        .styleCursor(false);
}

// آپلود تصویر
imageInput.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('image', file);
    formData.append('action', 'upload');

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        
        if (result.success) {
            createVisionItem(result.imagePath, 'image');
            saveItems();
        } else {
            alert('خطا در آپلود: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور');
    }

    e.target.value = '';
});

// ایجاد المان ویژن برد
function createVisionItem(content, type, x = 50, y = 50, width = 200, height = 150, id = null) {
    const itemId = id || Date.now().toString();
    
    const item = document.createElement('div');
    item.className = 'vision-item drag-handle';
    item.setAttribute('data-id', itemId);
    item.setAttribute('data-x', 0);
    item.setAttribute('data-y', 0);
    item.style.left = x + 'px';
    item.style.top = y + 'px';
    item.style.width = width + 'px';
    item.style.height = height + 'px';
    item.style.zIndex = Math.floor(Math.random() * 100) + 1;

    // دکمه حذف
    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'delete-btn';
    deleteBtn.innerHTML = '×';
    deleteBtn.onclick = (e) => {
        e.stopPropagation();
        deleteItem(itemId);
    };
    item.appendChild(deleteBtn);

    if (type === 'image') {
        const img = document.createElement('img');
        img.src = content;
        img.draggable = false;
        item.appendChild(img);
        
        // قابلیت تغییر سایز با دابل کلیک
        item.ondblclick = () => {
            const newWidth = prompt('عرض جدید (پیکسل):', width);
            const newHeight = prompt('ارتفاع جدید (پیکسل):', height);
            if (newWidth && newHeight) {
                item.style.width = parseInt(newWidth) + 'px';
                item.style.height = parseInt(newHeight) + 'px';
                saveItemPosition(itemId);
            }
        };
    } else if (type === 'text') {
        const textDiv = document.createElement('div');
        textDiv.className = 'text-content';
        textDiv.contentEditable = true;
        textDiv.innerText = content;
        textDiv.onblur = () => {
            saveItemPosition(itemId);
        };
        item.appendChild(textDiv);
    }

    // افزایش z-index هنگام کلیک
    item.addEventListener('mousedown', () => {
        const maxZ = Math.max(...Array.from(document.querySelectorAll('.vision-item'))
            .map(el => parseInt(el.style.zIndex) || 1));
        item.style.zIndex = maxZ + 1;
        saveItemPosition(itemId);
    });

    board.appendChild(item);

    // اضافه کردن به آرایه items
    const existingIndex = items.findIndex(i => i.id === itemId);
    if (existingIndex >= 0) {
        items[existingIndex] = { id: itemId, type, content, x, y, width, height };
    } else {
        items.push({ id: itemId, type, content, x, y, width, height });
    }

    // فعال‌سازی مجدد interact برای المان جدید
    interact(item).unset();
    setupInteract();
}

// افزودن متن
function addTextElement() {
    const text = prompt('متن انگیزشی خود را وارد کنید:');
    if (text) {
        const x = Math.random() * (board.offsetWidth - 200);
        const y = Math.random() * (board.offsetHeight - 150);
        createVisionItem(text, 'text', x, y, 200, 100);
        saveItems();
    }
}

// ذخیره موقعیت آیتم
async function saveItemPosition(id) {
    const item = document.querySelector(`[data-id="${id}"]`);
    if (!item) return;

    const x = parseFloat(item.getAttribute('data-x')) || 0;
    const y = parseFloat(item.getAttribute('data-y')) || 0;
    const width = parseInt(item.style.width) || 200;
    const height = parseInt(item.style.height) || 150;
    const zIndex = parseInt(item.style.zIndex) || 1;

    const itemData = items.find(i => i.id === id);
    if (itemData) {
        itemData.x = parseFloat(item.style.left) || 0;
        itemData.y = parseFloat(item.style.top) || 0;
        itemData.width = width;
        itemData.height = height;
        itemData.zIndex = zIndex;
        
        // اگر محتوای متن تغییر کرده باشد
        const textContent = item.querySelector('.text-content');
        if (textContent) {
            itemData.content = textContent.innerText;
        }
    }

    saveItems();
}

// ذخیره همه آیتم‌ها
async function saveItems() {
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'save',
                items: items
            })
        });

        const result = await response.json();
        if (!result.success) {
            console.error('Save error:', result.message);
        }
    } catch (error) {
        console.error('Save error:', error);
    }
}

// بارگذاری آیتم‌ها
async function loadItems() {
    try {
        const response = await fetch('api.php?action=load');
        const result = await response.json();

        if (result.success && result.items) {
            items = result.items;
            board.innerHTML = '';
            
            items.forEach(item => {
                createVisionItem(
                    item.content,
                    item.type,
                    item.x,
                    item.y,
                    item.width,
                    item.height,
                    item.id
                );
            });
        }
    } catch (error) {
        console.error('Load error:', error);
    }
}

// حذف آیتم
function deleteItem(id) {
    if (confirm('آیا مطمئن هستید که می‌خواهید این مورد را حذف کنید؟')) {
        const item = document.querySelector(`[data-id="${id}"]`);
        if (item) {
            item.remove();
        }
        items = items.filter(i => i.id !== id);
        saveItems();
    }
}

// پاک کردن همه
function clearBoard() {
    if (confirm('آیا مطمئن هستید که می‌خواهید همه موارد را حذف کنید؟')) {
        board.innerHTML = '';
        items = [];
        saveItems();
    }
}
