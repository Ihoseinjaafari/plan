/**
 * Vision Board JavaScript
 * Handles drag & drop, CRUD operations, and user interactions
 */

class VisionBoard {
    constructor() {
        this.board = document.getElementById('visionBoard');
        this.addItemBtn = document.getElementById('addItemBtn');
        this.clearAllBtn = document.getElementById('clearAllBtn');
        this.addItemModal = document.getElementById('addItemModal');
        this.editItemModal = document.getElementById('editItemModal');
        this.addItemForm = document.getElementById('addItemForm');
        this.editItemForm = document.getElementById('editItemForm');
        this.contextMenu = document.getElementById('contextMenu');
        
        this.draggedItem = null;
        this.offset = { x: 0, y: 0 };
        this.userId = this.getUserId();
        
        this.init();
    }
    
    getUserId() {
        // In a real app, this would come from authentication
        let userId = localStorage.getItem('visionUserId');
        if (!userId) {
            userId = 'user_' + Date.now();
            localStorage.setItem('visionUserId', userId);
        }
        return userId;
    }
    
    init() {
        this.bindEvents();
        this.loadItems();
    }
    
    bindEvents() {
        // Add item button
        this.addItemBtn.addEventListener('click', () => this.openAddModal());
        
        // Clear all button
        this.clearAllBtn.addEventListener('click', () => this.clearAll());
        
        // Modal close buttons
        document.querySelectorAll('.close-btn, .close-modal').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.closeModals();
            });
        });
        
        // Close modal on outside click
        window.addEventListener('click', (e) => {
            if (e.target === this.addItemModal || e.target === this.editItemModal) {
                this.closeModals();
            }
            // Close context menu
            if (!this.contextMenu.contains(e.target)) {
                this.contextMenu.classList.remove('active');
            }
        });
        
        // Form submissions
        this.addItemForm.addEventListener('submit', (e) => this.handleAddItem(e));
        this.editItemForm.addEventListener('submit', (e) => this.handleEditItem(e));
        
        // Image preview
        document.getElementById('itemImage').addEventListener('change', (e) => {
            this.previewImage(e, 'imagePreview');
        });
        document.getElementById('editImage').addEventListener('change', (e) => {
            this.previewImage(e, 'editImagePreview');
        });
        
        // Drag and drop on board
        this.board.addEventListener('dragover', (e) => e.preventDefault());
        this.board.addEventListener('drop', (e) => this.handleDrop(e));
        
        // Context menu
        this.board.addEventListener('contextmenu', (e) => this.handleContextMenu(e));
        
        // Context menu actions
        this.contextMenu.querySelectorAll('li').forEach(item => {
            item.addEventListener('click', (e) => this.handleContextAction(e));
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModals();
                this.contextMenu.classList.remove('active');
            }
        });
    }
    
    async loadItems() {
        try {
            const response = await fetch(`vision/api.php?action=get&user_id=${this.userId}`);
            const data = await response.json();
            
            if (data.success && data.items.length > 0) {
                this.board.innerHTML = '';
                data.items.forEach(item => this.createVisionItem(item));
            } else {
                this.showEmptyState();
            }
        } catch (error) {
            console.error('Error loading items:', error);
            this.showEmptyState();
        }
    }
    
    showEmptyState() {
        this.board.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <h3>ویژن برد شما خالی است</h3>
                <p>اولین آیتم خود را اضافه کنید تا تخته رویاهای شما شروع به شکل‌گیری کند!</p>
            </div>
        `;
    }
    
    openAddModal() {
        this.addItemForm.reset();
        document.getElementById('imagePreview').innerHTML = '';
        this.addItemModal.classList.add('active');
    }
    
    closeModals() {
        this.addItemModal.classList.remove('active');
        this.editItemModal.classList.remove('active');
    }
    
    previewImage(event, previewId) {
        const file = event.target.files[0];
        const preview = document.getElementById(previewId);
        
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
        }
    }
    
    async handleAddItem(e) {
        e.preventDefault();
        
        const formData = new FormData(this.addItemForm);
        formData.append('user_id', this.userId);
        
        try {
            const response = await fetch('vision/api.php?action=add', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.closeModals();
                // Remove empty state if present
                const emptyState = this.board.querySelector('.empty-state');
                if (emptyState) {
                    this.board.innerHTML = '';
                }
                this.createVisionItem(data.item);
            } else {
                alert('خطا در افزودن آیتم: ' + (data.error || 'نامشخص'));
            }
        } catch (error) {
            console.error('Error adding item:', error);
            alert('خطا در ارتباط با سرور');
        }
    }
    
    async handleEditItem(e) {
        e.preventDefault();
        
        const itemId = document.getElementById('editItemId').value;
        const formData = new FormData(this.editItemForm);
        formData.append('user_id', this.userId);
        
        try {
            const response = await fetch('vision/api.php?action=update', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.closeModals();
                // Update the item in the DOM
                const existingItem = this.board.querySelector(`[data-id="${itemId}"]`);
                if (existingItem) {
                    existingItem.remove();
                }
                this.createVisionItem(data.item);
            } else {
                alert('خطا در ویرایش آیتم: ' + (data.error || 'نامشخص'));
            }
        } catch (error) {
            console.error('Error updating item:', error);
            alert('خطا در ارتباط با سرور');
        }
    }
    
    createVisionItem(item) {
        const itemEl = document.createElement('div');
        itemEl.className = 'vision-item';
        itemEl.setAttribute('data-id', item.id);
        itemEl.style.left = item.position.x + 'px';
        itemEl.style.top = item.position.y + 'px';
        itemEl.style.width = item.size.width + 'px';
        itemEl.style.height = item.size.height + 'px';
        itemEl.draggable = true;
        
        let contentHtml = '';
        
        if (item.type === 'image' && item.image) {
            contentHtml = `<img src="../${item.image}" class="vision-item-image" alt="Vision item">`;
        } else if (item.type === 'mixed' && item.image) {
            contentHtml = `
                <img src="../${item.image}" class="vision-item-image" alt="Vision item">
                <div class="vision-item-text">${item.content}</div>
            `;
        } else {
            contentHtml = `<div class="vision-item-text">${item.content || 'متن خود را وارد کنید'}</div>`;
        }
        
        itemEl.innerHTML = `
            <div class="vision-item-controls">
                <button class="control-btn edit" title="ویرایش">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="control-btn delete" title="حذف">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="vision-item-content ${item.type === 'mixed' ? 'vision-item-mixed' : ''}">
                ${contentHtml}
            </div>
        `;
        
        // Make draggable
        itemEl.addEventListener('dragstart', (e) => this.handleDragStart(e, item));
        itemEl.addEventListener('dragend', (e) => this.handleDragEnd(e, item));
        
        // Control buttons
        itemEl.querySelector('.edit').addEventListener('click', (e) => {
            e.stopPropagation();
            this.openEditModal(item);
        });
        
        itemEl.querySelector('.delete').addEventListener('click', (e) => {
            e.stopPropagation();
            this.deleteItem(item.id);
        });
        
        this.board.appendChild(itemEl);
    }
    
    handleDragStart(e, item) {
        this.draggedItem = e.currentTarget;
        this.draggedItem.classList.add('dragging');
        
        const rect = this.draggedItem.getBoundingClientRect();
        const boardRect = this.board.getBoundingClientRect();
        
        this.offset = {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
        
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', item.id);
    }
    
    handleDragEnd(e, item) {
        if (!this.draggedItem) return;
        
        this.draggedItem.classList.remove('dragging');
        
        const boardRect = this.board.getBoundingClientRect();
        const newX = this.draggedItem.offsetLeft;
        const newY = this.draggedItem.offsetTop;
        
        // Save new position
        this.savePosition(item.id, { x: newX, y: newY });
        
        this.draggedItem = null;
    }
    
    handleDrop(e) {
        e.preventDefault();
        // Drop handling is done in dragend
    }
    
    async savePosition(itemId, position) {
        try {
            const response = await fetch('vision/api.php?action=reorder', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify([{
                    id: itemId,
                    position: position
                }])
            });
            
            const data = await response.json();
            if (!data.success) {
                console.error('Failed to save position');
            }
        } catch (error) {
            console.error('Error saving position:', error);
        }
    }
    
    handleContextMenu(e) {
        e.preventDefault();
        
        const item = e.target.closest('.vision-item');
        if (!item) return;
        
        const itemId = item.getAttribute('data-id');
        
        this.contextMenu.style.left = e.clientX + 'px';
        this.contextMenu.style.top = e.clientY + 'px';
        this.contextMenu.setAttribute('data-item-id', itemId);
        this.contextMenu.classList.add('active');
    }
    
    handleContextAction(e) {
        const action = e.currentTarget.getAttribute('data-action');
        const itemId = this.contextMenu.getAttribute('data-item-id');
        
        switch (action) {
            case 'edit':
                this.openEditModalById(itemId);
                break;
            case 'delete':
                this.deleteItem(itemId);
                break;
            case 'bring-front':
                this.bringToFront(itemId);
                break;
        }
        
        this.contextMenu.classList.remove('active');
    }
    
    async openEditModalById(itemId) {
        try {
            const response = await fetch(`vision/api.php?action=get&user_id=${this.userId}`);
            const data = await response.json();
            
            if (data.success) {
                const item = data.items.find(i => i.id === itemId);
                if (item) {
                    this.openEditModal(item);
                }
            }
        } catch (error) {
            console.error('Error fetching item:', error);
        }
    }
    
    openEditModal(item) {
        document.getElementById('editItemId').value = item.id;
        document.getElementById('editContent').value = item.content || '';
        document.getElementById('editImagePreview').innerHTML = item.image 
            ? `<img src="../${item.image}" alt="Current image">` 
            : '';
        
        this.editItemModal.classList.add('active');
    }
    
    async deleteItem(itemId) {
        if (!confirm('آیا مطمئن هستید که می‌خواهید این آیتم را حذف کنید؟')) {
            return;
        }
        
        try {
            const response = await fetch(`vision/api.php?action=delete&id=${itemId}&user_id=${this.userId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                const itemEl = this.board.querySelector(`[data-id="${itemId}"]`);
                if (itemEl) {
                    itemEl.remove();
                }
                
                // Show empty state if no items left
                if (this.board.querySelectorAll('.vision-item').length === 0) {
                    this.showEmptyState();
                }
            } else {
                alert('خطا در حذف آیتم: ' + (data.error || 'نامشخص'));
            }
        } catch (error) {
            console.error('Error deleting item:', error);
            alert('خطا در ارتباط با سرور');
        }
    }
    
    bringToFront(itemId) {
        const itemEl = this.board.querySelector(`[data-id="${itemId}"]`);
        if (itemEl) {
            const maxZIndex = Math.max(
                ...Array.from(this.board.querySelectorAll('.vision-item'))
                    .map(el => parseInt(window.getComputedStyle(el).zIndex) || 1)
            );
            itemEl.style.zIndex = maxZIndex + 1;
        }
    }
    
    async clearAll() {
        if (!confirm('آیا مطمئن هستید که می‌خواهید تمام آیتم‌ها را حذف کنید؟ این عملیات قابل بازگشت نیست.')) {
            return;
        }
        
        try {
            const response = await fetch(`vision/api.php?action=get&user_id=${this.userId}`);
            const data = await response.json();
            
            if (data.success && data.items.length > 0) {
                // Delete all items one by one
                for (const item of data.items) {
                    await fetch(`vision/api.php?action=delete&id=${item.id}&user_id=${this.userId}`, {
                        method: 'DELETE'
                    });
                }
                
                this.board.innerHTML = '';
                this.showEmptyState();
            }
        } catch (error) {
            console.error('Error clearing all items:', error);
            alert('خطا در ارتباط با سرور');
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new VisionBoard();
});
