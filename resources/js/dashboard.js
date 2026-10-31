/*
 * Tableau de bord analytique : à chaque changement de filtre, relit les
 * données réelles (DashboardController::analytics, JSON) puis met à jour
 * indicateurs, graphiques, tableau croisé et statistiques d'opérations.
 *
 * - Les filtres sont gardés dans l'adresse de la page : un rechargement les conserve.
 * - Une requête plus récente annule la précédente (AbortController).
 * - Le bouton « Valeurs / % du total / % de la ligne » ne relit rien : les
 *   pourcentages, calculés par le serveur, sont déjà dans la réponse.
 * - Une valeur absente (total nul : division impossible) s'affiche « — ».
 * - Les couleurs viennent des variables CSS --dash-* (resources/css/_palette.scss) :
 *   graphiques, indicateurs et tableaux utilisent la même teinte pour la même notion.
 */

// Teinte de chaque statut (rapprochement et résultats de rapprochement).
const STATUS_TONES = { matched: 'emerald', partial: 'cyan', conflict: 'rose', unmatched: 'amber', ignored: 'slate', rejected: 'slate' };
// Teintes des séries sans couleur métier (sources, types d'exception…), dans cet ordre.
const SERIES_TONES = ['violet', 'blue', 'amber', 'cyan', 'rose', 'fuchsia', 'emerald', 'orange', 'slate'];
const DEBOUNCE_MS = 300;

const cssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const tone = (name) => [cssVar(`--dash-${name}`), cssVar(`--dash-${name}-2`)];
const rgba = (hex, alpha) => {
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

/**
 * Couleurs partagées avec les graphiques écrits dans la vue (window.dashboardTheme).
 * gradient() : dégradé de la teinte, calculé sur la zone du graphique (vertical, ou horizontal).
 * area() : remplissage translucide sous une courbe.
 */
const theme = {
    color: (name) => tone(name)[0],
    statusColor: (status) => tone(STATUS_TONES[status] ?? 'slate')[0],
    seriesColor: (index) => tone(SERIES_TONES[index % SERIES_TONES.length])[0],
    gradient: (name, horizontal = false) => (context) => {
        const [from, to] = tone(name);
        const { ctx, chartArea } = context.chart;
        if (!chartArea) {
            return from;
        }
        const fill = horizontal
            ? ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0)
            : ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
        fill.addColorStop(0, from);
        fill.addColorStop(1, to);

        return fill;
    },
    area: (name) => (context) => {
        const { ctx, chartArea } = context.chart;
        if (!chartArea) {
            return 'transparent';
        }
        const fill = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        fill.addColorStop(0, rgba(tone(name)[0], 0.25));
        fill.addColorStop(1, rgba(tone(name)[0], 0));

        return fill;
    },
};
window.dashboardTheme = theme;

/**
 * Style commun à tous les graphiques : police, légende en pastilles, barres arrondies, anneau fin.
 * Appelé par app.js dès l'enregistrement de Chart.js, avant la création de tout graphique.
 */
export function applyChartDefaults(Chart) {
    const text = cssVar('--bs-secondary-color');
    const grid = cssVar('--bs-border-color-translucent');
    const background = cssVar('--bs-body-bg');

    Chart.defaults.font.family = cssVar('--bs-body-font-family') || '"Inter Variable", system-ui, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = text;
    Chart.defaults.borderColor = grid;
    Chart.defaults.scale.grid.color = grid;
    Chart.defaults.scale.border.display = false;
    Chart.defaults.plugins.legend.position = 'bottom';
    Object.assign(Chart.defaults.plugins.legend.labels, { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16 });
    Object.assign(Chart.defaults.plugins.tooltip, {
        backgroundColor: 'rgba(30, 27, 58, .92)', padding: 10, cornerRadius: 8, boxPadding: 4, usePointStyle: true,
    });
    Object.assign(Chart.defaults.elements.bar, { borderRadius: 6, borderSkipped: false, maxBarThickness: 44 });
    Object.assign(Chart.defaults.elements.arc, { borderWidth: 4, borderColor: background, borderRadius: 4 });
    Object.assign(Chart.defaults.elements.line, { borderWidth: 2.5 });
    Object.assign(Chart.defaults.elements.point, { radius: 0, hoverRadius: 5 });
    Chart.overrides.doughnut.cutout = '72%';
}

// Thème clair/sombre changé dans la barre du haut : les graphiques reprennent les nouvelles couleurs.
new MutationObserver(() => {
    if (window.Chart) {
        applyChartDefaults(window.Chart);
        Object.values(window.Chart.instances).forEach((chart) => chart.update());
    }
}).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

const number = (value) => (value === null || value === undefined ? '—' : Number(value).toLocaleString('fr-FR'));
const tnd = (millimes) => (millimes === null || millimes === undefined ? '—'
    : (Number(millimes) / 1000).toLocaleString('fr-FR', { minimumFractionDigits: 3, maximumFractionDigits: 3 }));
const percent = (value) => (value === null || value === undefined ? '—'
    : `${Number(value).toLocaleString('fr-FR', { maximumFractionDigits: value > 0 && value < 1 ? 3 : 1 })} %`);
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

function duration(seconds) {
    if (seconds === null || seconds === undefined) {
        return '—';
    }
    const minutes = Math.floor(seconds / 60);

    return minutes > 0 ? `${minutes} min ${String(seconds % 60).padStart(2, '0')} s` : `${seconds} s`;
}

/** Paramètres de la requête à partir du formulaire (les cases cochées deviennent sources[]=…). */
function queryFrom(form) {
    const params = new URLSearchParams();
    for (const [name, value] of new FormData(form).entries()) {
        if (value !== '') {
            params.append(name, value);
        }
    }

    return params;
}

/** Remet dans le formulaire les filtres présents dans l'adresse de la page. */
function restoreFilters(form) {
    const params = new URLSearchParams(window.location.search);
    for (const element of form.elements) {
        if (!element.name) {
            continue;
        }
        const values = params.getAll(element.name);
        if (element.type === 'checkbox') {
            element.checked = values.includes(element.value);
        } else if (values.length > 0) {
            element.value = values[0];
        }
    }
}

/** Crée le graphique au premier appel, puis remplace seulement ses données. */
function drawChart(charts, id, config) {
    const canvas = document.getElementById(id);
    if (!canvas) {
        return;
    }
    if (charts[id]) {
        charts[id].data = config.data;
        charts[id].update();

        return;
    }
    charts[id] = new window.Chart(canvas, config);
}

function renderKpis(root, kpis) {
    const format = { montant_millimes: tnd, taux_rapprochement: percent, taux_anomalie: percent };
    root.querySelectorAll('[data-kpi]').forEach((element) => {
        const key = element.dataset.kpi;
        element.textContent = (format[key] ?? number)(kpis[key]);
    });
}

function renderCharts(charts, data) {
    const timeline = data.evolution;
    document.querySelector('[data-timeline-granularity]').textContent = timeline.granularite === 'jour' ? '(par jour)' : '(par mois)';
    drawChart(charts, 'chart-analytics-timeline', {
        type: 'bar',
        data: {
            labels: timeline.periodes,
            datasets: timeline.series.map((serie) => ({ label: serie.libelle, data: serie.valeurs, backgroundColor: theme.gradient(STATUS_TONES[serie.statut] ?? 'slate') })),
        },
        options: { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } },
    });

    const statuses = data.indicateurs.par_statut.filter((row) => row.lignes > 0);
    drawChart(charts, 'chart-analytics-status', {
        type: 'doughnut',
        data: {
            labels: statuses.map((row) => `${row.libelle} (${percent(row.pourcentage)})`),
            datasets: [{ data: statuses.map((row) => row.lignes), backgroundColor: statuses.map((row) => theme.statusColor(row.statut)) }],
        },
    });

    const bySource = data.par_source;
    drawChart(charts, 'chart-analytics-source', {
        type: 'bar',
        data: {
            labels: bySource.lignes.map((row) => row.libelle),
            datasets: bySource.colonnes.map((column, c) => ({
                label: column.libelle,
                data: bySource.cellules.map((cells) => cells[c].valeur),
                backgroundColor: theme.gradient(STATUS_TONES[column.cle] ?? SERIES_TONES[c % SERIES_TONES.length], true),
            })),
        },
        options: { indexAxis: 'y', scales: { x: { stacked: true, beginAtZero: true }, y: { stacked: true } } },
    });
}

/** Tableau croisé : valeurs, % du total général ou % de la ligne, avec totaux. */
function renderPivot(root, pivot, mode) {
    const table = root.querySelector('[data-pivot-table]');
    const format = (cell) => {
        if (mode !== 'valeur') {
            return percent(cell[mode] ?? null);
        }

        return pivot.mesure === 'montant' ? tnd(cell.valeur) : number(cell.valeur);
    };
    const totalCell = { valeur: pivot.total, pct_total: pivot.total > 0 ? 100 : null, pct_ligne: pivot.total > 0 ? 100 : null };
    const head = `<thead><tr><th></th>${pivot.colonnes.map((c) => `<th class="text-end">${escapeHtml(c.libelle)}</th>`).join('')}<th class="text-end">Total</th></tr></thead>`;
    const body = pivot.lignes.length === 0
        ? `<tbody><tr><td colspan="${pivot.colonnes.length + 2}" class="text-center text-secondary py-3">Aucune donnée pour ces filtres.</td></tr></tbody>`
        : `<tbody>${pivot.lignes.map((row, r) => `<tr><th scope="row">${escapeHtml(row.libelle)}</th>${pivot.cellules[r].map((cell) => `<td class="text-end">${format(cell)}</td>`).join('')}<td class="text-end fw-semibold">${format(pivot.totaux_lignes[r])}</td></tr>`).join('')}</tbody>`;
    // En « % de la ligne », la ligne Total donne la répartition de l'ensemble (identique au % du total).
    const columnTotal = (cell) => format(mode === 'pct_ligne' ? { ...cell, pct_ligne: cell.pct_total } : cell);
    const foot = pivot.lignes.length === 0 ? '' : `<tfoot><tr><th scope="row">Total</th>${pivot.totaux_colonnes.map((cell) => `<td class="text-end">${columnTotal(cell)}</td>`).join('')}<td class="text-end">${format(totalCell)}</td></tr></tfoot>`;
    table.innerHTML = head + body + foot;
    root.querySelector('[data-pivot-note]').textContent = pivot.tronque
        ? 'Tableau limité aux 200 premières valeurs : affinez les filtres.'
        : (mode === 'valeur' ? '' : (mode === 'pct_total' ? 'Pourcentages calculés sur le total général.' : 'Pourcentages calculés sur le total de chaque ligne.'));
}

function renderOperations(root, operations) {
    const format = { taux_succes: percent };
    root.querySelectorAll('[data-operation-kpi]').forEach((element) => {
        const key = element.dataset.operationKpi;
        element.textContent = (format[key] ?? number)(operations[key]);
    });
    root.querySelector('[data-operations-types] tbody').innerHTML = operations.par_type.length === 0
        ? '<tr><td colspan="5" class="text-center text-secondary py-3">Aucune opération pour ces filtres.</td></tr>'
        : operations.par_type.map((row) => `<tr><td>${escapeHtml(row.libelle)}</td><td class="text-end">${number(row.total)}</td><td class="text-end">${number(row.terminees)}</td><td class="text-end">${number(row.echecs)}</td><td class="text-end">${duration(row.duree_moyenne_secondes)}</td></tr>`).join('');
    root.querySelector('[data-operations-failures]').innerHTML = operations.derniers_echecs.length === 0
        ? '<li class="list-group-item text-secondary">Aucun échec.</li>'
        : operations.derniers_echecs.map((failure) => `<li class="list-group-item"><div class="fw-semibold">${escapeHtml(failure.libelle)}</div>
            <div class="text-secondary">${escapeHtml(failure.date)}${failure.utilisateur ? ` · ${escapeHtml(failure.utilisateur)}` : ''}</div>
            <div class="dash-failure-error">${escapeHtml(failure.erreur)}</div></li>`).join('');
}

function initDashboard(root) {
    const form = root.querySelector('#analytics-filters');
    const status = root.querySelector('[data-analytics-status]');
    const charts = {};
    let pivotMode = 'valeur';
    let lastData = null;
    let controller = null;
    let timer = null;

    const load = async (refresh = false) => {
        const params = queryFrom(form);
        window.history.replaceState(null, '', `${window.location.pathname}${params.size ? `?${params}` : ''}`);
        if (refresh) {
            params.set('actualiser', '1');
        }
        controller?.abort();
        controller = new AbortController();
        status.textContent = 'Chargement…';
        status.classList.remove('text-danger');
        try {
            const response = await fetch(`${root.dataset.url}?${params}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
            const data = await response.json();
            if (!response.ok) {
                // Erreur de validation : message en français renvoyé par Laravel.
                throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Erreur de chargement.');
            }
            lastData = data;
            if (data.rapprochement) {
                renderKpis(root, data.rapprochement.indicateurs);
                renderCharts(charts, data.rapprochement);
                renderPivot(root, data.rapprochement.croise, pivotMode);
            }
            renderOperations(root, data.operations);
            status.textContent = data.rapprochement ? `Données calculées le ${data.rapprochement.calcule_le}` : '';
        } catch (error) {
            if (error.name !== 'AbortError') {
                status.textContent = error.message;
                status.classList.add('text-danger');
            }
        }
    };
    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(() => load(), DEBOUNCE_MS);
    };

    restoreFilters(form);
    form.addEventListener('change', schedule);
    // Les menus du tableau croisé sont rattachés au formulaire (attribut form=) mais placés
    // ailleurs dans la page : leurs événements ne remontent pas jusqu'au formulaire.
    document.querySelectorAll(`[form="${form.id}"]`).forEach((element) => element.addEventListener('change', schedule));
    root.querySelector('[data-analytics-reset]')?.addEventListener('click', () => {
        form.reset();
        load();
    });
    root.querySelector('[data-analytics-refresh]')?.addEventListener('click', () => load(true));
    root.querySelectorAll('[data-pivot-mode] [data-mode]').forEach((button) => button.addEventListener('click', () => {
        pivotMode = button.dataset.mode;
        root.querySelectorAll('[data-pivot-mode] [data-mode]').forEach((b) => b.classList.toggle('active', b === button));
        if (lastData?.rapprochement) {
            renderPivot(root, lastData.rapprochement.croise, pivotMode);
        }
    }));
    load();
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('dashboard-analytics');
    if (root) {
        initDashboard(root);
    }
});
