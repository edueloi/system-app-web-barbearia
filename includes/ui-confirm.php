<?php
// includes/ui-confirm.php
?>
<style>
    .app-confirm-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.55);
        backdrop-filter: blur(2px);
        z-index: 2100;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity .22s;
    }
    .app-confirm-overlay.app-confirm-open {
        display: flex;
        opacity: 1;
    }
    .app-confirm-box {
        background: #ffffff;
        border-radius: 24px;
        padding: 18px 18px 14px 18px;
        max-width: 360px;
        box-shadow: 0 20px 40px rgba(15,23,42,0.45);
        transform: translateY(18px);
        transition: transform .22s;
    }
    .app-confirm-overlay.app-confirm-open .app-confirm-box {
        transform: translateY(0);
    }
    .app-confirm-title {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0 0 6px 0;
        color: #0f172a;
    }
    .app-confirm-message {
        font-size: 0.9rem;
        color: #475569;
        margin: 0 0 14px 0;
    }
    .app-confirm-actions {
        display: flex;
        gap: 10px;
        margin-top: 6px;
    }
    .app-confirm-btn {
        flex: 1;
        padding: 10px 0;
        border-radius: 999px;
        border: none;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
    }
    .app-confirm-btn-cancel {
        background: #f8fafc;
        color: #475569;
    }
    .app-confirm-btn-ok {
        background: #dc2626;
        color: #ffffff;
    }
    .app-confirm-success .app-confirm-btn-ok {
        background: #16a34a;
    }

    @media (min-width: 768px) {
        .app-confirm-box {
            border-radius: 22px;
        }
    }
</style>

<div class="app-confirm-overlay" id="appConfirmOverlay">
    <div class="app-confirm-box" id="appConfirmBox">
        <h4 class="app-confirm-title" id="appConfirmTitle">Confirmar ação</h4>
        <p class="app-confirm-message" id="appConfirmMessage">Deseja continuar?</p>
        <div class="app-confirm-actions">
            <button type="button" class="app-confirm-btn app-confirm-btn-cancel" id="appConfirmCancel">Cancelar</button>
            <button type="button" class="app-confirm-btn app-confirm-btn-ok" id="appConfirmOk">OK</button>
        </div>
    </div>
</div>

<script>
    window.AppConfirm = (function () {
        const overlay   = document.getElementById('appConfirmOverlay');
        const box       = document.getElementById('appConfirmBox');
        const titleEl   = document.getElementById('appConfirmTitle');
        const messageEl = document.getElementById('appConfirmMessage');
        const btnOk     = document.getElementById('appConfirmOk');
        const btnCancel = document.getElementById('appConfirmCancel');

        let onConfirmCb = null;

        function open(opts) {
            const {
                title       = 'Confirmar ação',
                message     = 'Deseja continuar?',
                confirmText = 'OK',
                cancelText  = 'Cancelar',
                type        = 'danger', // 'danger' | 'success'
                onConfirm   = null,
            } = opts || {};

            titleEl.innerText     = title;
            messageEl.innerHTML   = message;
            btnOk.innerText       = confirmText;
            btnCancel.innerText   = cancelText;
            onConfirmCb           = onConfirm;

            box.classList.remove('app-confirm-success');
            if (type === 'success') {
                box.classList.add('app-confirm-success');
            }

            overlay.classList.add('app-confirm-open');
        }

        function close() {
            overlay.classList.remove('app-confirm-open');
            onConfirmCb = null;
        }

        btnOk.addEventListener('click', () => {
            if (typeof onConfirmCb === 'function') onConfirmCb();
            close();
        });

        btnCancel.addEventListener('click', close);

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });

        return { open, close };
    })();
</script>
