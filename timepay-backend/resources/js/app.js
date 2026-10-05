import './bootstrap';

import Alpine from 'alpinejs';
import { timePayErrorMessages } from './errorMessages';

window.Alpine = Alpine;
window.TimePayErrors = timePayErrorMessages;

Alpine.start();
