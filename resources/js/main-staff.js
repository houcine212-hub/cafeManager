import { bindLocaleSwitcher } from './core/i18n.js';
import { bindConnectionIndicator, bindClock } from './core/connectivity.js';
import { initStaffViews } from './core/staff-views.js';
import { initStaffDashboard } from './staff/dashboard.js';
import { initStaffOrderQueue } from './staff/order-queue.js';

bindLocaleSwitcher();
bindConnectionIndicator(document.querySelector('[data-connection-indicator]'));
bindClock(document.querySelector('[data-clock]'));
initStaffViews();
initStaffDashboard();
initStaffOrderQueue();
