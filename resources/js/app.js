import './bootstrap';
import flatpickr from "flatpickr";
import Chart from 'chart.js/auto';
import { initFlowbite } from 'flowbite';
import {
    destroyWhatsAppEmbeddedSignup,
    initializeWhatsAppEmbeddedSignup,
} from './whatsapp-embedded-signup';

// Jadikan global agar Alpine.js di file Blade bisa memanggil fungsi flatpickr() dan Chart
window.Chart = Chart;
window.flatpickr = flatpickr;

const sidebarBreakpoint = window.matchMedia('(min-width: 640px)');
const sidebarStorageKey = 'dashboard_sidebar_open';
const responsiveCompactColumnPattern = /^(?:no\.?|nomor|#)$/i;

let destroyDashboardSidebar = () => {};

function responsiveHeaderText(header) {
    const clone = header.cloneNode(true);

    clone.querySelectorAll('svg, [aria-hidden="true"], .sr-only').forEach((element) => element.remove());

    return clone.textContent
        .replace(/[\u25B2\u25BC\u2191\u2193]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function responsiveColumnLabels(table) {
    const headerRows = Array.from(table.tHead?.rows ?? []);
    const grid = [];

    headerRows.forEach((row, rowIndex) => {
        grid[rowIndex] ??= [];
        let columnIndex = 0;

        Array.from(row.cells).forEach((header) => {
            while (grid[rowIndex][columnIndex] !== undefined) {
                columnIndex += 1;
            }

            const label = header.dataset.responsiveLabel || responsiveHeaderText(header);
            const columnSpan = Math.max(header.colSpan, 1);
            const rowSpan = Math.max(header.rowSpan, 1);

            for (let rowOffset = 0; rowOffset < rowSpan; rowOffset += 1) {
                grid[rowIndex + rowOffset] ??= [];

                for (let columnOffset = 0; columnOffset < columnSpan; columnOffset += 1) {
                    grid[rowIndex + rowOffset][columnIndex + columnOffset] = label;
                }
            }

            columnIndex += columnSpan;
        });
    });

    const columnCount = Math.max(0, ...grid.map((row) => row.length));

    return Array.from({ length: columnCount }, (_, columnIndex) => {
        return [...new Set(grid.map((row) => row[columnIndex]).filter(Boolean))].join(' / ');
    });
}

function annotateResponsiveRows(rows, labels, compactColumnIndexes) {
    const occupiedUntilRow = [];

    Array.from(rows).forEach((row, rowIndex) => {
        let columnIndex = 0;

        Array.from(row.cells).forEach((cell) => {
            while ((occupiedUntilRow[columnIndex] ?? 0) > rowIndex) {
                columnIndex += 1;
            }

            const columnSpan = Math.max(cell.colSpan, 1);
            const rowSpan = Math.max(cell.rowSpan, 1);

            if (!cell.hasAttribute('data-responsive-label')) {
                cell.dataset.responsiveLabel = columnSpan === 1 ? (labels[columnIndex] ?? '') : '';
            }

            if (cell.dataset.responsiveLabel) {
                cell.setAttribute('aria-label', cell.dataset.responsiveLabel);
            }

            cell.toggleAttribute(
                'data-responsive-compact-column',
                columnSpan === 1 && compactColumnIndexes.has(columnIndex),
            );

            if (rowSpan > 1) {
                for (let columnOffset = 0; columnOffset < columnSpan; columnOffset += 1) {
                    occupiedUntilRow[columnIndex + columnOffset] = rowIndex + rowSpan;
                }
            }

            columnIndex += columnSpan;
        });
    });
}

function initializeResponsiveTables(root = document) {
    const tables = [];

    if (root instanceof Element && root.matches('table[data-responsive-table]')) {
        tables.push(root);
    }

    root?.querySelectorAll?.('table[data-responsive-table]').forEach((table) => tables.push(table));

    tables.forEach((table) => {
        const labels = responsiveColumnLabels(table);
        const compactColumnIndexes = new Set(
            labels.flatMap((label, columnIndex) => {
                const labelParts = label.split('/').map((part) => part.trim());

                return labelParts.some((part) => responsiveCompactColumnPattern.test(part))
                    ? [columnIndex]
                    : [];
            }),
        );

        table.querySelectorAll('thead th').forEach((header) => {
            header.toggleAttribute(
                'data-responsive-compact-column',
                responsiveCompactColumnPattern.test(responsiveHeaderText(header)),
            );
        });

        Array.from(table.tBodies).forEach((body) => annotateResponsiveRows(body.rows, labels, compactColumnIndexes));

        if (table.tFoot) {
            annotateResponsiveRows(table.tFoot.rows, labels, compactColumnIndexes);
        }
    });
}

function initializeDashboardSidebar() {
    destroyDashboardSidebar();

    const sidebar = document.getElementById('top-bar-sidebar');
    const content = document.getElementById('dashboard-content');
    const toggleButton = document.querySelector('[data-sidebar-toggle="top-bar-sidebar"]');

    if (!sidebar || !content || !toggleButton) {
        destroyDashboardSidebar = () => {};

        return;
    }

    const toggleLabel = toggleButton.querySelector('[data-sidebar-toggle-label]');
    let isOpen = sidebarBreakpoint.matches
        && localStorage.getItem(sidebarStorageKey) !== 'false';
    let backdrop = null;

    const removeBackdrop = () => {
        backdrop?.remove();
        backdrop = null;
    };

    const updateSidebar = (open, persistDesktopState = false) => {
        isOpen = open;

        sidebar.classList.toggle('translate-x-0', isOpen);
        sidebar.classList.toggle('-translate-x-full', !isOpen);
        sidebar.setAttribute('aria-hidden', String(!isOpen));

        content.classList.toggle('sm:ml-64', isOpen);
        content.classList.toggle('sm:ml-0', !isOpen);

        toggleButton.setAttribute('aria-expanded', String(isOpen));

        if (toggleLabel) {
            toggleLabel.textContent = isOpen ? 'Tutup sidebar' : 'Buka sidebar';
        }

        removeBackdrop();

        if (isOpen && !sidebarBreakpoint.matches) {
            backdrop = document.createElement('button');
            backdrop.type = 'button';
            backdrop.className = 'fixed inset-0 z-30 bg-gray-900/50 sm:hidden';
            backdrop.setAttribute('aria-label', 'Tutup sidebar');
            backdrop.addEventListener('click', () => updateSidebar(false));
            document.body.append(backdrop);
        }

        if (persistDesktopState && sidebarBreakpoint.matches) {
            localStorage.setItem(sidebarStorageKey, String(isOpen));
        }
    };

    const handleToggle = () => updateSidebar(!isOpen, true);
    const handleEscape = (event) => {
        if (event.key === 'Escape' && isOpen && !sidebarBreakpoint.matches) {
            updateSidebar(false);
            toggleButton.focus();
        }
    };
    const handleBreakpointChange = (event) => {
        const shouldOpen = event.matches
            && localStorage.getItem(sidebarStorageKey) !== 'false';

        updateSidebar(shouldOpen);
    };

    toggleButton.addEventListener('click', handleToggle);
    document.addEventListener('keydown', handleEscape);
    sidebarBreakpoint.addEventListener('change', handleBreakpointChange);

    updateSidebar(isOpen);

    destroyDashboardSidebar = () => {
        toggleButton.removeEventListener('click', handleToggle);
        document.removeEventListener('keydown', handleEscape);
        sidebarBreakpoint.removeEventListener('change', handleBreakpointChange);
        removeBackdrop();
    };
}

function restoreSidebarScrollPosition() {
    const sidebar = document.querySelector('#top-bar-sidebar div.overflow-y-auto');

    if (!sidebar) {
        return;
    }

    const saved = sessionStorage.getItem('sidebar_scroll');

    if (saved !== null) {
        sidebar.scrollTop = parseInt(saved, 10);
    }
}

function initializePageUi() {
    initFlowbite();
    initializeDashboardSidebar();
    restoreSidebarScrollPosition();
    initializeResponsiveTables();
    initializeWhatsAppEmbeddedSignup();
}

document.addEventListener('livewire:init', () => {
    Livewire.hook('morphed', ({ el }) => {
        initializeResponsiveTables(el);
        initializeWhatsAppEmbeddedSignup();
    });
    Livewire.hook('partial.morphed', ({ startNode }) => {
        initializeResponsiveTables(startNode.parentElement);
        initializeWhatsAppEmbeddedSignup();
    });
}, { once: true });

// Persist sidebar scroll position across wire:navigate
document.addEventListener('livewire:navigating', () => {
    destroyWhatsAppEmbeddedSignup();

    const sidebar = document.querySelector('#top-bar-sidebar div.overflow-y-auto');
    if (sidebar) {
        sessionStorage.setItem('sidebar_scroll', sidebar.scrollTop);
    }
});

document.addEventListener('livewire:navigated', () => {
    initializePageUi();
});

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.Livewire === 'undefined') {
        initializePageUi();
    }
}, { once: true });
