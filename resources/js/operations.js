/*
 * Suivi en temps réel des opérations longues (import, rapprochement,
 * comparaison, exports) : voir app/Services/OperationTracker.php.
 *
 * Le navigateur relit l'avancement toutes les 2 secondes (« polling »),
 * uniquement tant qu'une opération est en attente ou en cours, et pas quand
 * l'onglet est masqué. Toutes les valeurs affichées viennent du serveur :
 * sans total connu, aucun pourcentage n'est inventé (on affiche seulement le
 * nombre d'éléments traités et un indicateur d'activité).
 *
 * Trois usages dans les pages :
 *   - [data-operations-indicator] : pastille « opérations en cours » de la barre du haut ;
 *   - [data-operation-progress]   : barre de progression d'une opération dans une page ;
 *   - [data-operations-list]      : tableau de la page « Mes opérations ».
 */

const POLL_MS = 2000;

const SUMMARY_LABELS = {
    acceptees: 'acceptées',
    rejetees: 'rejetées',
    rapprochements: 'rapprochements',
    rapprochees: 'rapprochées',
    conflits: 'conflits',
    exclusives_a: 'écarts fichier A',
    exclusives_b: 'écarts fichier B',
    references: 'références',
    lignes: 'lignes exportées',
};

const STATUS_CLASSES = { pending: 'secondary', running: 'primary', completed: 'success', failed: 'danger' };

const number = (value) => Number(value ?? 0).toLocaleString('fr-FR');

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}

function duration(seconds) {
    if (seconds === null || seconds === undefined) {
        return '';
    }
    const minutes = Math.floor(seconds / 60);
    const rest = String(seconds % 60).padStart(2, '0');

    return minutes > 0 ? `${minutes} min ${rest} s` : `${seconds} s`;
}

/** Badge de statut. */
export function statusBadge(op) {
    return `<span class="badge text-bg-${STATUS_CLASSES[op.status] ?? 'secondary'}">${escapeHtml(op.status_label)}</span>`;
}

/** Barre de progression et texte d'avancement, à partir des seules valeurs du serveur. */
export function progressHtml(op) {
    const parts = [];
    const counts = op.total !== null && op.total !== undefined
        ? `${number(op.processed)} / ${number(op.total)} ${escapeHtml(op.unit)} traitées`
        : `${number(op.processed)} ${escapeHtml(op.unit)} traitées`;

    if (op.status === 'pending') {
        parts.push(`<div class="small text-secondary"><i class="bi bi-hourglass me-1"></i>En attente du traitement (file d’attente), créée le ${escapeHtml(op.created_at)}.</div>`);
    } else if (op.status === 'running' && op.percent !== null) {
        parts.push(`<div class="progress" role="progressbar" aria-label="${escapeHtml(op.label)}" aria-valuenow="${op.percent}" aria-valuemin="0" aria-valuemax="100" style="height: 1.1rem;">
            <div class="progress-bar" style="width: ${op.percent}%">${op.percent} %</div></div>`);
        parts.push(`<div class="small text-secondary mt-1">${counts} — ${op.percent} %${op.stage ? ` · ${escapeHtml(op.stage)}` : ''}</div>`);
    } else if (op.status === 'running') {
        // Avancement non mesurable (total inconnu ou étape comme l'écriture du fichier).
        parts.push(`<div class="small"><span class="spinner-border spinner-border-sm text-primary me-1" aria-hidden="true"></span>
            ${escapeHtml(op.stage ?? 'En cours')} — ${counts}</div>`);
    } else if (op.status === 'completed') {
        const summary = Object.entries(op.summary ?? {})
            .filter(([, value]) => value !== null)
            .map(([key, value]) => `${number(value)} ${escapeHtml(SUMMARY_LABELS[key] ?? key)}`)
            .join(' · ');
        parts.push(`<div class="progress" style="height: 1.1rem;"><div class="progress-bar bg-success" style="width: 100%">100 %</div></div>`);
        parts.push(`<div class="small text-secondary mt-1">${summary || counts}</div>`);
    } else if (op.status === 'failed') {
        parts.push(`<div class="small text-danger"><i class="bi bi-x-octagon me-1"></i>${escapeHtml(op.error_message ?? 'Échec du traitement.')}</div>`);
        if (op.processed > 0) {
            parts.push(`<div class="small text-secondary">Déjà traité avant l’échec : ${counts}.</div>`);
        }
    }

    if (op.stale) {
        parts.push(`<div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Aucune nouvelle du traitement depuis le ${escapeHtml(op.heartbeat_at ?? op.started_at)} : le worker de file d’attente est peut-être arrêté.</div>`);
    }

    return parts.join('');
}

/** Message bref (« toast ») quand une opération suivie sur la page se termine ou échoue. */
function toast(op) {
    let container = document.getElementById('operations-toasts');
    if (!container) {
        container = document.createElement('div');
        container.id = 'operations-toasts';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    const failed = op.status === 'failed';
    const element = document.createElement('div');
    element.className = `toast align-items-center text-bg-${failed ? 'danger' : 'success'} border-0`;
    element.setAttribute('role', failed ? 'alert' : 'status');
    element.innerHTML = `<div class="d-flex"><div class="toast-body">
        <strong>${escapeHtml(op.label)}</strong> : ${failed ? `échec — ${escapeHtml(op.error_message ?? '')}` : 'terminé.'}
        ${op.result_url && !failed ? ` <a class="link-light" href="${escapeHtml(op.result_url)}">Voir les résultats</a>` : ''}
        </div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button></div>`;
    container.appendChild(element);
    new window.bootstrap.Toast(element, { autohide: !failed, delay: 8000 }).show();
}

/** Relit une URL JSON toutes les 2 s tant que onData() renvoie vrai (opérations encore actives). */
function poll(url, onData) {
    const tick = async () => {
        if (document.hidden) {
            setTimeout(tick, POLL_MS);

            return;
        }
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.ok && onData(await response.json()) === false) {
                return; // plus rien d'actif : on arrête de relire
            }
        } catch {
            // Réseau momentanément indisponible : on réessaie au prochain tour.
        }
        setTimeout(tick, POLL_MS);
    };
    setTimeout(tick, POLL_MS);
}

/** Retient le dernier statut connu de chaque opération pour signaler fins et échecs. */
const knownStatuses = new Map();
function noticeTransitions(operations) {
    operations.forEach((op) => {
        const previous = knownStatuses.get(op.uuid);
        if (previous && ['pending', 'running'].includes(previous) && ['completed', 'failed'].includes(op.status)) {
            toast(op);
        }
        knownStatuses.set(op.uuid, op.status);
    });
}

function initIndicator(element) {
    const badge = element.querySelector('[data-operations-count]');
    const render = (count) => {
        badge.textContent = count;
        badge.classList.toggle('d-none', count === 0);
    };
    render(Number(element.dataset.activeCount));
    if (Number(element.dataset.activeCount) === 0) {
        return;
    }
    poll(element.dataset.url, (data) => {
        render(data.active_count);
        noticeTransitions(data.operations);

        return data.active_count > 0;
    });
}

function initProgress(element) {
    const render = (op) => {
        element.innerHTML = progressHtml(op);
        element.closest('[data-operation-card]')?.querySelector('[data-operation-status]')
            ?.replaceChildren(document.createRange().createContextualFragment(statusBadge(op)));
    };
    const initial = JSON.parse(element.dataset.operation);
    render(initial);
    knownStatuses.set(initial.uuid, initial.status);
    if (!initial.active) {
        return;
    }
    poll(element.dataset.url, (op) => {
        render(op);
        noticeTransitions([op]);
        if (!op.active && element.dataset.reloadOnFinish === '1') {
            // Les résultats de la page (bilan, tableaux) viennent d'être calculés : on les affiche.
            setTimeout(() => window.location.reload(), 1500);
        }

        return op.active;
    });
}

function initList(element) {
    const showUser = element.dataset.showUser === '1';
    const body = element.querySelector('tbody');
    const render = (operations) => {
        body.innerHTML = operations.length === 0
            ? `<tr><td colspan="${showUser ? 6 : 5}" class="text-center text-secondary py-3">Aucune opération pour le moment.</td></tr>`
            : operations.map((op) => `<tr>
                <td><div class="fw-semibold">${escapeHtml(op.label)}</div><div class="small text-secondary">${escapeHtml(op.type_label)}</div></td>
                <td>${statusBadge(op)}</td>
                <td style="min-width: 16rem;">${progressHtml(op)}</td>
                <td class="small">${escapeHtml(op.started_at ?? op.created_at)}<div class="text-secondary">${duration(op.duration_seconds)}</div></td>
                ${showUser ? `<td class="small">${escapeHtml(op.user ?? '—')}</td>` : ''}
                <td>${op.result_url && op.status === 'completed' ? `<a class="btn btn-sm btn-outline-primary" href="${escapeHtml(op.result_url)}">Résultats</a>` : ''}</td>
            </tr>`).join('');
    };
    const initial = JSON.parse(element.dataset.operations);
    render(initial);
    noticeTransitions(initial);
    if (!initial.some((op) => op.active)) {
        return;
    }
    poll(element.dataset.url, (data) => {
        render(data.operations);
        noticeTransitions(data.operations);

        return data.operations.some((op) => op.active);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-operations-indicator]').forEach(initIndicator);
    document.querySelectorAll('[data-operation-progress]').forEach(initProgress);
    document.querySelectorAll('[data-operations-list]').forEach(initList);
});
