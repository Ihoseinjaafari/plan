// Habits/script.js - اسکریپت‌های ماژول عادت‌ها

document.addEventListener('DOMContentLoaded', function() {
    // مدیریت ناوبری داخلی
    document.querySelectorAll('.nav-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            document.querySelectorAll('.nav-link').forEach(function(l) {
                l.classList.remove('active');
            });
            
            this.classList.add('active');
            
            const page = this.dataset.page;
            if (page) {
                window.history.pushState({ page: page }, '', '?page=' + page);
                loadPage(page);
            }
        });
    });
    
    // بارگذاری صفحه بر اساس URL
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page') || 'dashboard';
    
    // به‌روزرسانی لینک فعال
    document.querySelectorAll('.nav-link').forEach(function(link) {
        if (link.dataset.page === page) {
            link.classList.add('active');
        }
    });
});

function loadPage(page) {
    const contentDiv = document.querySelector('.habits-content');
    contentDiv.innerHTML = '<div style="text-align: center; padding: 50px;">در حال بارگذاری...</div>';
    
    fetch('index.php?page=' + page)
        .then(response => response.text())
        .then(html => {
            // استخراج محتوای اصلی از پاسخ
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const mainContent = doc.querySelector('.habits-dashboard') || 
                               doc.querySelector('.reminders-page') ||
                               doc.querySelector('.focus-page') ||
                               doc.querySelector('.routines-page') ||
                               doc.querySelector('.analytics-page');
            
            if (mainContent) {
                contentDiv.innerHTML = mainContent.outerHTML;
                
                // اجرای مجدد اسکریپت‌ها
                const scripts = mainContent.getElementsByTagName('script');
                for (let script of scripts) {
                    const newScript = document.createElement('script');
                    if (script.src) {
                        newScript.src = script.src;
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    document.body.appendChild(newScript);
                }
            } else {
                contentDiv.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error loading page:', error);
            contentDiv.innerHTML = '<div style="text-align: center; padding: 50px; color: var(--danger-color);">خطا در بارگذاری صفحه</div>';
        });
}

// پشتیبانی از دکمه بازگشت مرورگر
window.addEventListener('popstate', function(event) {
    if (event.state && event.state.page) {
        const page = event.state.page;
        
        document.querySelectorAll('.nav-link').forEach(function(link) {
            link.classList.toggle('active', link.dataset.page === page);
        });
        
        loadPage(page);
    }
});
