import { bindLocaleSwitcher } from './core/i18n.js';
import { bindConnectionIndicator, bindClock } from './core/connectivity.js';
import { initStaffViews } from './core/staff-views.js';
import { initStaffDashboard } from './staff/dashboard.js';
import { initStaffOrderQueue } from './staff/order-queue.js';
import { initStaffServiceRequests } from './staff/service-requests.js';
import { initStaffTables } from './staff/tables.js';

bindLocaleSwitcher();
bindConnectionIndicator(document.querySelector('[data-connection-indicator]'));
bindClock(document.querySelector('[data-clock]'));
initStaffViews();
initStaffDashboard();
initStaffOrderQueue();
initStaffServiceRequests();
initStaffTables();
