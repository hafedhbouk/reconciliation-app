import './bootstrap';

import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

import $ from 'jquery';

window.$ = window.jQuery = $;

import 'datatables.net-bs5';

// Textes des tableaux DataTables en français, pour tous les tableaux de l'application.
$.extend(true, $.fn.dataTable.defaults, {
    language: {
        processing: 'Traitement en cours…',
        search: 'Rechercher :',
        lengthMenu: 'Afficher _MENU_ lignes',
        info: 'Lignes _START_ à _END_ sur _TOTAL_',
        infoEmpty: 'Aucune ligne',
        infoFiltered: '(filtrées parmi _MAX_ au total)',
        loadingRecords: 'Chargement…',
        zeroRecords: 'Aucun résultat ne correspond à la recherche',
        emptyTable: 'Aucune donnée disponible',
        thousands: ' ',
        decimal: ',',
        paginate: { first: 'Première page', previous: 'Page précédente', next: 'Page suivante', last: 'Dernière page' },
        aria: { orderable: 'Activer pour trier la colonne', orderableReverse: 'Activer pour inverser le tri' },
    },
});

import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;

// Suivi en temps réel des opérations longues (barres de progression, « Mes opérations »).
import './operations';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        new bootstrap.Tooltip(el);
    });
});
