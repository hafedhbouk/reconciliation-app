import './bootstrap';

import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

import $ from 'jquery';

window.$ = window.jQuery = $;

import 'datatables.net-bs5';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        new bootstrap.Tooltip(el);
    });
});
