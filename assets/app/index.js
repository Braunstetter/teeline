import { startStimulusApp } from '@symfony/reprise/stimulus';
import '@hotwired/turbo';

import './styles/app.css';

startStimulusApp();

// Only when FrankenPHP announced a hot-reload endpoint, which it does in dev. The dynamic
// import keeps both libraries in their own chunk, so production never requests them.
if (document.querySelector('meta[name="frankenphp-hot-reload:url"]')) {
    const { Idiomorph } = await import('idiomorph/dist/idiomorph.esm.js');

    window.Idiomorph = Idiomorph;

    await import('frankenphp-hot-reload');
}
