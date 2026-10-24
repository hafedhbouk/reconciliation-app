/*
 * Chatbot métier : fenêtre « Assistant » (resources/views/components/chatbot.blade.php).
 *
 * La question est envoyée au serveur (ChatbotController), qui calcule la
 * réponse sur les données réelles avec les droits de l'utilisateur. Ce script
 * n'affiche que ce que le serveur renvoie : texte, détail chiffré, méthode de
 * calcul et questions suggérées. Tout est échappé avant affichage.
 */

const STATUS_CLASSES = {
    ok: 'bg-body-tertiary',
    vide: 'bg-body-tertiary',
    non_calculable: 'bg-warning-subtle',
    interdit: 'bg-danger-subtle',
    non_pris_en_charge: 'bg-warning-subtle',
    erreur: 'bg-danger-subtle',
};

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

function initChatbot(panel) {
    const messages = panel.querySelector('[data-chatbot-messages]');
    const form = panel.querySelector('[data-chatbot-form]');
    const input = form.querySelector('input[name="question"]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    const scroll = () => { messages.scrollTop = messages.scrollHeight; };
    const suggestions = (list) => (list.length === 0 ? '' : `<div class="d-flex flex-wrap gap-1 mt-2">${list.map((text) =>
        `<button type="button" class="btn btn-sm btn-outline-primary text-start" data-chatbot-suggestion>${escapeHtml(text)}</button>`).join('')}</div>`);

    const addUser = (text) => {
        messages.insertAdjacentHTML('beforeend', `<div class="d-flex justify-content-end mb-2"><div class="rounded-3 bg-primary text-white px-3 py-2" style="max-width: 85%;">${escapeHtml(text)}</div></div>`);
        scroll();
    };
    const addAnswer = (answer) => {
        const details = (answer.details ?? []).length === 0 ? ''
            : `<table class="table table-sm small mb-1 mt-2"><tbody>${answer.details.map((row) =>
                `<tr><td>${escapeHtml(row.libelle)}</td><td class="text-end text-nowrap">${escapeHtml(row.valeur)}</td></tr>`).join('')}</tbody></table>`;
        const basis = answer.calcul ? `<div class="small text-secondary mt-1"><i class="bi bi-info-circle me-1"></i>${escapeHtml(answer.calcul)}</div>` : '';
        messages.insertAdjacentHTML('beforeend', `<div class="mb-3"><div class="rounded-3 px-3 py-2 ${STATUS_CLASSES[answer.statut] ?? 'bg-body-tertiary'}">
            <div>${escapeHtml(answer.texte)}</div>${details}${basis}${suggestions(answer.suggestions ?? [])}</div></div>`);
        scroll();
    };

    const ask = async (question) => {
        addUser(question);
        input.value = '';
        input.disabled = true;
        const pending = document.createElement('div');
        pending.className = 'small text-secondary mb-2';
        pending.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Calcul en cours…';
        messages.appendChild(pending);
        try {
            const response = await fetch(panel.dataset.url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ question }),
            });
            const data = await response.json();
            if (!response.ok) {
                // Validation (question vide, trop longue) ou trop de questions en peu de temps.
                const message = response.status === 429 ? 'Trop de questions en peu de temps : patientez une minute.'
                    : (Object.values(data.errors ?? {}).flat()[0] ?? 'La question n’a pas pu être traitée.');
                addAnswer({ statut: 'erreur', texte: message });
            } else {
                addAnswer(data);
            }
        } catch {
            addAnswer({ statut: 'erreur', texte: 'Le serveur ne répond pas. Vérifiez votre connexion puis réessayez.' });
        } finally {
            pending.remove();
            input.disabled = false;
            input.focus();
        }
    };

    addAnswer({
        statut: 'ok',
        texte: 'Bonjour ! Posez une question sur les rapprochements, les lignes importées, les exceptions ou vos traitements. Je ne donne que des chiffres calculés sur les données que vous avez le droit de consulter.',
        suggestions: JSON.parse(panel.dataset.examples ?? '[]'),
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const question = input.value.trim();
        if (question !== '') {
            ask(question);
        }
    });
    messages.addEventListener('click', (event) => {
        const button = event.target.closest('[data-chatbot-suggestion]');
        if (button && !input.disabled) {
            ask(button.textContent);
        }
    });
    panel.addEventListener('shown.bs.offcanvas', () => input.focus());
}

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('[data-chatbot]');
    if (panel) {
        initChatbot(panel);
    }
});
