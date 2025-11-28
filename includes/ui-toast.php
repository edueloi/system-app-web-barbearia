<?php
// includes/ui-toast.php
?>
<style>
    .app-toast-container {
        position: fixed;
        top: 72px;
        right: 16px;
        z-index: 2500;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    @media (max-width: 768px) {
        .app-toast-container {
            top: auto;
            bottom: 76px;
            right: 16px;
            left: 16px;
        }
    }
    .app-toast {
        min-width: 220px;
        max-width: 340px;
        padding: 9px 11px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.86rem;
        box-shadow: 0 14px 28px rgba(15,23,42,0.6);
        animation: app-toast-in .22s ease-out, app-toast-out .22s ease-in 3.3s forwards;
    }
    .app-toast-success { background: #dcfce7; color: #166534; }
    .app-toast-danger  { background: #fee2e2; color: #b91c1c; }
    .app-toast-info    { background: #e2f3ff; color: #075985; }

    .app-toast-icon { font-size: 1rem; }
    .app-toast-message { flex: 1; }
    .app-toast-close {
        background: transparent;
        border: none;
        font-size: 1rem;
        cursor: pointer;
        color: inherit;
    }

    @keyframes app-toast-in {
        from { opacity: 0; transform: translateY(8px) scale(0.98); }
        to   { opacity: 1; transform: translateY(0)   scale(1); }
    }
    @keyframes app-toast-out {
        to { opacity: 0; transform: translateY(-6px) scale(0.98); }
    }
</style>

<div class="app-toast-container" id="appToastContainer"></div>

<script>
    window.AppToast = (function () {
        const container = document.getElementById('appToastContainer');

        function show(message, type = 'success') {
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = 'app-toast app-toast-' + type;

            const icon = document.createElement('span');
            icon.className = 'app-toast-icon';
            icon.innerHTML = type === 'danger' ? '⚠️' : (type === 'info' ? 'ℹ️' : '✅');

            const msg = document.createElement('span');
            msg.className = 'app-toast-message';
            msg.innerText = message;

            const btn = document.createElement('button');
            btn.className = 'app-toast-close';
            btn.innerHTML = '&times;';
            btn.addEventListener('click', () => {
                toast.style.animation = 'app-toast-out .2s forwards';
                setTimeout(() => toast.remove(), 180);
            });

            toast.appendChild(icon);
            toast.appendChild(msg);
            toast.appendChild(btn);
            container.appendChild(toast);

            setTimeout(() => {
                if (!toast.parentNode) return;
                toast.style.animation = 'app-toast-out .2s forwards';
                setTimeout(() => toast.remove(), 180);
            }, 3400);
        }

        return { show };
    })();
</script>
