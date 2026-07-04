<?php
// includes/icons.php - آیکون‌های جایگزین برای کل سایت
?>
<style>
    /* ===== آیکون‌های جایگزین با CSS ===== */
    
    /* ===== آیکون‌های عمومی ===== */
    .icon-seedling::before { content: "🌱"; }
    .icon-bell::before { content: "🔔"; }
    .icon-moon::before { content: "🌙"; }
    .icon-sun::before { content: "☀️"; }
    .icon-user::before { content: "👤"; }
    .icon-cog::before { content: "⚙️"; }
    .icon-tasks::before { content: "📋"; }
    .icon-compass::before { content: "🧭"; }
    .icon-project::before { content: "📊"; }
    .icon-calendar::before { content: "📅"; }
    .icon-logout::before { content: "🚪"; }
    .icon-login::before { content: "🔑"; }
    .icon-bars::before { content: "☰"; }
    .icon-search::before { content: "🔍"; }
    .icon-plus::before { content: "➕"; }
    .icon-edit::before { content: "✏️"; }
    .icon-trash::before { content: "🗑️"; }
    .icon-save::before { content: "💾"; }
    .icon-close::before { content: "❌"; }
    .icon-check::before { content: "✅"; }
    .icon-filter::before { content: "🔽"; }
    .icon-home::before { content: "🏠"; }
    .icon-fire::before { content: "🔥"; }
    .icon-tag::before { content: "🏷️"; }
    .icon-clock::before { content: "🕐"; }
    .icon-calendar-alt::before { content: "📆"; }
    .icon-external-link::before { content: "🔗"; }
    .icon-file-csv::before { content: "📄"; }
    .icon-shield-alt::before { content: "🛡️"; }
    .icon-arrow-left::before { content: "◀️"; }
    .icon-arrow-right::before { content: "▶️"; }
    .icon-sync::before { content: "🔄"; }
    .icon-undo::before { content: "↩️"; }
    .icon-info-circle::before { content: "ℹ️"; }
    .icon-folder-open::before { content: "📂"; }
    .icon-pen::before { content: "🖊️"; }
    .icon-plus-circle::before { content: "➕"; }
    .icon-trash-alt::before { content: "🗑️"; }
    .icon-sitemap::before { content: "🗺️"; }
    .icon-grip-vertical::before { content: "⠿"; }
    .icon-th-large::before { content: "▦"; }
    .icon-list::before { content: "☰"; }
    .icon-chevron-right::before { content: "◀"; }
    .icon-chevron-left::before { content: "▶"; }
    .icon-calendar-day::before { content: "📅"; }
    .icon-calendar-plus::before { content: "📆"; }
    .icon-calendar-week::before { content: "📅"; }
    .icon-calendar-minus::before { content: "📅"; }
    .icon-check-circle::before { content: "✅"; }
    .icon-inbox::before { content: "📥"; }
    .icon-arrows-alt::before { content: "⤵"; }
    .icon-trophy::before { content: "🏆"; }
    .icon-database::before { content: "🗄️"; }
    .icon-code::before { content: "</>"; }
    .icon-map-signs::before { content: "🗺️"; }
    .icon-external-link-alt::before { content: "🔗"; }
    .icon-file-pdf::before { content: "📄"; }
    .icon-key::before { content: "🔑"; }
    .icon-export::before { content: "📤"; }
    .icon-tools::before { content: "🔧"; }
    .icon-align-left::before { content: "☰"; }
    .icon-edit::before { content: "✏️"; }

    /* ===== آیکون‌های اضافی برای پلنر ===== */
    .icon-project-diagram::before { content: "📊"; }
    .icon-tags::before { content: "🏷️"; }
    .icon-fire::before { content: "🔥"; }
    .icon-file-csv::before { content: "📄"; }
    .icon-shield-alt::before { content: "🛡️"; }
    .icon-filter::before { content: "🔽"; }
    .icon-calendar-day::before { content: "📅"; }
    .icon-calendar-plus::before { content: "📆"; }
    .icon-calendar-week::before { content: "📅"; }
    .icon-calendar-minus::before { content: "📅"; }
    .icon-check-circle::before { content: "✅"; }
    .icon-list::before { content: "☰"; }
    .icon-th-large::before { content: "▦"; }
    .icon-arrows-alt::before { content: "⤵"; }
    .icon-sitemap::before { content: "🗺️"; }
    .icon-grip-vertical::before { content: "⠿"; }
    .icon-tag::before { content: "🏷️"; }
    .icon-clock::before { content: "🕐"; }
    .icon-calendar-alt::before { content: "📆"; }
    .icon-save::before { content: "💾"; }
    .icon-plus-circle::before { content: "➕"; }
    .icon-inbox::before { content: "📥"; }
    .icon-edit::before { content: "✏️"; }
    .icon-trash::before { content: "🗑️"; }
    .icon-close::before { content: "❌"; }
    .icon-check::before { content: "✅"; }
    .icon-plus::before { content: "➕"; }
    .icon-undo::before { content: "↩️"; }
    .icon-info-circle::before { content: "ℹ️"; }
    .icon-key::before { content: "🔑"; }
    .icon-export::before { content: "📤"; }
    .icon-tools::before { content: "🔧"; }
    .icon-align-left::before { content: "☰"; }

    /* ===== کلاس‌های عمومی آیکون ===== */
    .icon {
        display: inline-block;
        font-size: inherit;
        line-height: 1;
        font-style: normal;
        font-weight: normal;
        font-variant: normal;
        text-rendering: auto;
        -webkit-font-smoothing: antialiased;
    }

    /* ===== اندازه‌های مختلف آیکون ===== */
    .icon-sm { font-size: 12px; }
    .icon-md { font-size: 16px; }
    .icon-lg { font-size: 20px; }
    .icon-xl { font-size: 24px; }
    .icon-2xl { font-size: 32px; }

    /* ===== آیکون‌های با رنگ ===== */
    .icon-primary { color: #667eea; }
    .icon-success { color: #28a745; }
    .icon-danger { color: #dc3545; }
    .icon-warning { color: #ffc107; }
    .icon-info { color: #17a2b8; }

    /* ===== آیکون‌های با افکت hover ===== */
    .icon-hover:hover {
        transform: scale(1.15);
        transition: transform 0.3s ease;
    }
</style>