import './bootstrap';

import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/sora/500.css';
import '@fontsource/sora/600.css';
import '@fontsource/sora/700.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

import { registerUi } from './ui';

registerUi(Alpine);

Livewire.start();
