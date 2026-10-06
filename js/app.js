/* GTM Tracker - app.js */

// Toggle sidebar on mobile
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        // Create overlay if needed
        let overlay = document.querySelector('.sidebar-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }
        overlay.classList.toggle('active');
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    });
}

// Auto-dismiss flash messages
const flash = document.getElementById('flashMsg');
if (flash) {
    setTimeout(() => {
        flash.style.opacity = '0';
        flash.style.transform = 'translateY(10px)';
        flash.style.transition = 'all .4s ease';
        setTimeout(() => flash.remove(), 400);
    }, 4500);
}

// Confirm delete dialogs
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
        const msg = el.dataset.confirm || 'Confirmer cette action ?';
        if (!confirm(msg)) e.preventDefault();
    });
});

// Date default to today for date inputs without value
document.querySelectorAll('input[type="date"]:not([value])').forEach(input => {
    if (!input.value) {
        const today = new Date().toISOString().split('T')[0];
        input.value = today;
    }
});

// Animate stat cards on load
document.querySelectorAll('.stat-card').forEach((card, i) => {
    card.style.animationDelay = (i * 0.05) + 's';
    card.classList.add('animate-in');
});

// Simple bar chart renderer
function renderBarChart(canvasId, labels, data, color) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width = canvas.parentElement.offsetWidth;
    const H = canvas.height = 200;
    const max = Math.max(...data, 1);
    const padding = { top: 20, right: 20, bottom: 40, left: 40 };
    const chartW = W - padding.left - padding.right;
    const chartH = H - padding.top - padding.bottom;
    const barW = Math.max(20, (chartW / labels.length) - 12);

    ctx.clearRect(0, 0, W, H);

    // Grid lines
    ctx.strokeStyle = 'rgba(15,23,42,0.07)';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (chartH / 4) * i;
        ctx.beginPath(); ctx.moveTo(padding.left, y); ctx.lineTo(W - padding.right, y); ctx.stroke();
        ctx.fillStyle = '#64748b';
        ctx.font = '10px Inter, sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round(max - (max / 4) * i), padding.left - 6, y + 3);
    }

    // Bars
    data.forEach((val, i) => {
        const barH = (val / max) * chartH;
        const x = padding.left + i * (chartW / labels.length) + (chartW / labels.length - barW) / 2;
        const y = padding.top + chartH - barH;

        const grad = ctx.createLinearGradient(0, y, 0, y + barH);
        grad.addColorStop(0, color || '#6366f1');
        grad.addColorStop(1, (color || '#6366f1') + '55');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.roundRect(x, y, barW, barH, 4);
        ctx.fill();

        // Value label
        if (val > 0) {
            ctx.fillStyle = '#0f172a';
            ctx.font = '11px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(val, x + barW / 2, y - 6);
        }

        // X label
        ctx.fillStyle = '#475569';
        ctx.font = '10px Inter, sans-serif';
        ctx.textAlign = 'center';
        const label = labels[i].length > 10 ? labels[i].substring(0, 9) + '..' : labels[i];
        ctx.fillText(label, x + barW / 2, H - padding.bottom + 16);
    });
}

// Expose globally
window.renderBarChart = renderBarChart;
// --- PWA Universal Install Manager (Autorisation explicite de l'utilisateur) ---
(function() {
    // 1. Enregistrement Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/gtm-tracker/sw.js', { scope: '/gtm-tracker/' })
                .then(reg => console.log('GTM Tracker PWA SW ready:', reg.scope))
                .catch(err => console.warn('SW registration warning:', err));
        });
    }

    // 2. Si deja lancee en application installee (Standalone), on masque tout
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (isStandalone) {
        return;
    }

    const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
    let deferredPrompt = null;

    // Bouton permanent dans la barre laterale
    const sidebarBtn = document.getElementById('pwaManualInstallBtn');

    // 3. PC & Android : capture de l'evenement d'installation natif
    window.addEventListener('beforeinstallprompt', (e) => {
        // Empeche l'installation automatique : le navigateur DOIT attendre le clic utilisateur
        e.preventDefault();
        deferredPrompt = e;

        // Afficher le bouton dans le menu si present
        if (sidebarBtn) {
            sidebarBtn.style.display = 'inline-flex';
            sidebarBtn.onclick = () => triggerInstallPrompt();
        }

        // Proposer la banniere discrete de consentement
        if (!sessionStorage.getItem('pwa_prompt_dismissed')) {
            showConsentBanner('native');
        }
    });

    // 4. Sur iPhone / iPad Safari
    if (isIos) {
        const isSafari = /safari/.test(window.navigator.userAgent.toLowerCase()) && !/crios|fxios/.test(window.navigator.userAgent.toLowerCase());
        if (isSafari) {
            if (sidebarBtn) {
                sidebarBtn.style.display = 'inline-flex';
                sidebarBtn.onclick = () => showConsentBanner('ios');
            }
            if (!sessionStorage.getItem('pwa_prompt_dismissed')) {
                setTimeout(() => showConsentBanner('ios'), 3000);
            }
        }
    }

    // Action déclenchée uniquement après autorisation de l'utilisateur
    async function triggerInstallPrompt() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            if (choice.outcome === 'accepted') {
                console.log('Utilisateur a autorise l installation PWA');
                if (sidebarBtn) sidebarBtn.style.display = 'none';
            }
            deferredPrompt = null;
            const banner = document.getElementById('pwaInstallBanner');
            if (banner) banner.remove();
        }
    }

    function showConsentBanner(type) {
        if (document.getElementById('pwaInstallBanner')) return;

        const banner = document.createElement('div');
        banner.id = 'pwaInstallBanner';
        banner.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            left: 24px;
            max-width: 440px;
            margin: 0 auto;
            background: #ffffff;
            color: #0f172a;
            border: 1px solid rgba(99, 102, 241, 0.35);
            border-radius: 16px;
            padding: 16px 18px;
            box-shadow: 0 16px 40px -10px rgba(15, 23, 42, 0.22), 0 0 0 1px rgba(15, 23, 42, 0.05);
            display: flex;
            align-items: center;
            gap: 14px;
            z-index: 99999;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13.5px;
            animation: pwaSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        `;

        if (!document.getElementById('pwaAnimStyle')) {
            const st = document.createElement('style');
            st.id = 'pwaAnimStyle';
            st.textContent = `
                @keyframes pwaSlideUp {
                    from { opacity: 0; transform: translateY(24px) scale(0.97); }
                    to   { opacity: 1; transform: translateY(0) scale(1); }
                }
            `;
            document.head.appendChild(st);
        }

        if (type === 'native') {
            banner.innerHTML = `
                <div style="width:42px;height:42px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;flex-shrink:0;box-shadow:0 6px 16px -4px rgba(99,102,241,0.5)">
                    📲
                </div>
                <div style="flex:1;min-width:0">
                    <strong style="display:block;font-size:14px;color:#0f172a;font-weight:700">Installer GTM Tracker ?</strong>
                    <span style="font-size:12px;color:#64748b;display:block;margin-top:2px">Voulez-vous ajouter l'app sur votre écran d'accueil ?</span>
                </div>
                <button id="pwaInstallBtn" style="
                    background: linear-gradient(135deg,#6366f1,#4f46e5);
                    color: #fff;
                    border: none;
                    border-radius: 8px;
                    padding: 9px 15px;
                    font-size: 12.5px;
                    font-weight: 600;
                    cursor: pointer;
                    white-space: nowrap;
                    box-shadow: 0 4px 12px rgba(99,102,241,0.35);
                ">Autoriser</button>
                <button id="pwaDismissBtn" title="Refuser" style="
                    background: transparent;
                    border: none;
                    color: #94a3b8;
                    font-size: 20px;
                    line-height: 1;
                    cursor: pointer;
                    padding: 4px;
                    margin-left: 2px;
                ">&times;</button>
            `;
            document.body.appendChild(banner);

            document.getElementById('pwaInstallBtn').addEventListener('click', () => {
                triggerInstallPrompt();
            });
        } else if (type === 'ios') {
            banner.innerHTML = `
                <div style="width:42px;height:42px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;flex-shrink:0;box-shadow:0 6px 16px -4px rgba(99,102,241,0.5)">
                    🍏
                </div>
                <div style="flex:1;min-width:0">
                    <strong style="display:block;font-size:13.5px;color:#0f172a;font-weight:700">Installer sur iPhone</strong>
                    <span style="font-size:11.5px;color:#64748b;display:block;margin-top:2px">
                        Touchez <strong>Partager</strong> <span style="font-size:14px">⎋</span> puis <strong>« Sur l'écran d'accueil » ➕</strong>
                    </span>
                </div>
                <button id="pwaDismissBtn" style="
                    background: #f1f5f9;
                    color: #475569;
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    padding: 7px 12px;
                    font-size: 11.5px;
                    font-weight: 600;
                    cursor: pointer;
                    white-space: nowrap;
                ">Compris</button>
            `;
            document.body.appendChild(banner);
        }

        document.getElementById('pwaDismissBtn').addEventListener('click', () => {
            sessionStorage.setItem('pwa_prompt_dismissed', '1');
            banner.remove();
        });
    }
})();