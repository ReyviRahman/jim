import './bootstrap';
import flatpickr from "flatpickr";
import Chart from 'chart.js/auto';
import { initFlowbite } from 'flowbite';

// Jadikan global agar Alpine.js di file Blade bisa memanggil fungsi flatpickr() dan Chart
window.Chart = Chart;
window.flatpickr = flatpickr;

const sidebarBreakpoint = window.matchMedia('(min-width: 640px)');
const sidebarStorageKey = 'dashboard_sidebar_open';

let destroyDashboardSidebar = () => {};

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
}

// Persist sidebar scroll position across wire:navigate
document.addEventListener('livewire:navigating', () => {
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
