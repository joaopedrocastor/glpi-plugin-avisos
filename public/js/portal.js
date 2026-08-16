/**
 * Exibição dos avisos no portal de autoatendimento (seções 7 e 9).
 *
 * Camada de exibição isolada (seção 5): toda a regra de negócio vive no PHP;
 * aqui só há renderização e captura de interação.
 *
 * FALHA ABERTA (seção 16 / critério 14): todo o fluxo é envolto em try/catch e
 * promessas com .catch — qualquer erro é silenciado para nunca impedir o uso
 * do portal nem a abertura de chamados.
 */
(function () {
    'use strict';

    try {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', pluginAvisosStart);
        } else {
            pluginAvisosStart();
        }
    } catch (e) { /* falha aberta */ }

    function pluginAvisosBaseUrl() {
        var root = (window.CFG_GLPI && window.CFG_GLPI.root_doc) ? window.CFG_GLPI.root_doc : '';
        return root + '/plugins/avisos';
    }

    /**
     * Modal só na página inicial do portal (seção 9). Heurística por caminho;
     * pode ser ajustada conforme a home real do helpdesk no ambiente.
     */
    function pluginAvisosIsPortalHome() {
        var p = window.location.pathname.replace(/\/+$/, '');
        return /\/front\/(helpdesk\.public|central)\.php$/.test(p)
            || p === (window.CFG_GLPI && window.CFG_GLPI.root_doc ? window.CFG_GLPI.root_doc : '')
            || p === '';
    }

    function pluginAvisosStart() {
        try {
            if (!pluginAvisosIsPortalHome()) {
                return;
            }
            fetch(pluginAvisosBaseUrl() + '/ajax/getalerts.php', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && Array.isArray(data.alerts) && data.alerts.length) {
                        pluginAvisosRunQueue(data.alerts, 0);
                    }
                })
                .catch(function () { /* falha aberta */ });
        } catch (e) { /* falha aberta */ }
    }

    /**
     * Exibe um aviso por vez; ao fechar, chama o próximo (seção 9 — fila).
     */
    function pluginAvisosRunQueue(alerts, index) {
        if (index >= alerts.length) {
            return;
        }
        try {
            pluginAvisosShowModal(alerts[index], function () {
                pluginAvisosRunQueue(alerts, index + 1);
            });
        } catch (e) {
            // Se um modal falhar, tenta o próximo (falha aberta).
            pluginAvisosRunQueue(alerts, index + 1);
        }
    }

    function pluginAvisosShowModal(alert, onClose) {
        var behavior = alert.behavior;
        var isBlocking = behavior === 'blocking';
        var isAck = behavior === 'acknowledge';

        var overlay = document.createElement('div');
        overlay.className = 'avisos-overlay';

        var modal = document.createElement('div');
        modal.className = 'avisos-modal avisos-sev-' + (alert.severity || 'info');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        // Cabeçalho (título como TEXTO — nunca innerHTML, evita XSS no título).
        var header = document.createElement('div');
        header.className = 'avisos-modal-header';
        var h = document.createElement('h2');
        h.className = 'avisos-modal-title';
        h.textContent = alert.title || '';
        header.appendChild(h);

        // Botão X apenas para o comportamento informativo (seção 7.1).
        if (!isBlocking && !isAck) {
            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'avisos-modal-close';
            close.setAttribute('aria-label', 'Fechar');
            close.innerHTML = '&times;';
            close.addEventListener('click', function () {
                finish('close');
            });
            header.appendChild(close);
        }
        modal.appendChild(header);

        // Corpo: conteúdo já sanitizado no servidor (seção 12).
        var body = document.createElement('div');
        body.className = 'avisos-modal-body';
        body.innerHTML = alert.content || '';
        modal.appendChild(body);

        // Rodapé conforme comportamento.
        var footer = document.createElement('div');
        footer.className = 'avisos-modal-footer';

        if (isAck) {
            var label = document.createElement('label');
            label.className = 'avisos-ack';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'avisos-ack-check';
            var span = document.createElement('span');
            span.textContent = 'Li e estou ciente';
            label.appendChild(cb);
            label.appendChild(span);

            var confirm = document.createElement('button');
            confirm.type = 'button';
            confirm.className = 'avisos-btn avisos-btn-primary';
            confirm.textContent = 'Confirmar';
            confirm.disabled = true;
            cb.addEventListener('change', function () {
                confirm.disabled = !cb.checked;
            });
            confirm.addEventListener('click', function () {
                if (cb.checked) { finish('acknowledge'); }
            });
            footer.appendChild(label);
            footer.appendChild(confirm);
        } else if (isBlocking) {
            // Travante: sem controles de dispensa (seção 7.1).
            var note = document.createElement('div');
            note.className = 'avisos-blocking-note';
            note.textContent = '';
            footer.appendChild(note);
        } else {
            var ok = document.createElement('button');
            ok.type = 'button';
            ok.className = 'avisos-btn avisos-btn-primary';
            ok.textContent = 'Fechar';
            ok.addEventListener('click', function () { finish('close'); });
            footer.appendChild(ok);
        }
        modal.appendChild(footer);

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        document.body.classList.add('avisos-modal-open');

        // Clique fora: fecha só no informativo (seções 7.1).
        overlay.addEventListener('click', function (ev) {
            if (ev.target === overlay && !isBlocking && !isAck) {
                finish('close');
            }
        });

        // Esc: fecha só no informativo; travante e ciência ignoram.
        function onKey(ev) {
            if (ev.key === 'Escape' && !isBlocking && !isAck) {
                finish('close');
            }
            // Travante: bloqueia Esc explicitamente.
            if (ev.key === 'Escape' && isBlocking) {
                ev.preventDefault();
                ev.stopPropagation();
            }
        }
        document.addEventListener('keydown', onKey, true);

        var done = false;
        function finish(action) {
            if (done) { return; }
            done = true;
            document.removeEventListener('keydown', onKey, true);
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            document.body.classList.remove('avisos-modal-open');
            // Registra a interação (fire-and-forget, falha aberta).
            pluginAvisosRecord(alert, action);
            if (typeof onClose === 'function') { onClose(); }
        }
    }

    /**
     * Registra fechamento/ciência. Não bloqueia a fila se falhar (seção 16).
     */
    function pluginAvisosRecord(alert, action) {
        try {
            var body = 'alerts_id=' + encodeURIComponent(alert.id)
                + '&action=' + encodeURIComponent(action)
                + '&_glpi_csrf_token=' + encodeURIComponent(alert.csrf || '');
            fetch(pluginAvisosBaseUrl() + '/ajax/markread.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).catch(function () { /* falha aberta */ });
        } catch (e) { /* falha aberta */ }
    }
})();
