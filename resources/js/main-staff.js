import { bindLocaleSwitcher } from './core/i18n.js';
import { bindConnectionIndicator } from './core/connectivity.js';
import { initStaffDashboard } from './staff/dashboard.js';

bindLocaleSwitcher();
bindConnectionIndicator(document.querySelector('[data-connection-indicator]'));
initStaffDashboard();
