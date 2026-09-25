import {Controller} from "@hotwired/stimulus"

// Give up waiting for the challenge host at some point. Without a bound, a blocked
// or unreachable challenges.cloudflare.com leaves the interval running for as long
// as the page is open.
const TURNSTILE_TIMEOUT_MS = 10000;

export default class extends Controller {
  static values = {
    siteKey: String,
    disabledInfo: String,
    theme: String,
    size: String,
    action: String,
    language: String,
  };

  async connect() {
    if (this.button === null) {
      return;
    }

    await this.loadScript();

    // Blocked or slow host: leave the form usable instead of locking its button for
    // good. Registration and password reset are verified on the server anyway.
    if (!await this.waitForTurnstile()) {
      return;
    }

    this.button.disabled = true;
    if (this.disabledInfoValue !== "") {
      this.button.title = this.disabledInfoValue;
    }

    // Explicit rendering reads none of the data attributes on the element, so every
    // option has to be handed over right here.
    this.widgetId = window.turnstile.render(this.element, {
      sitekey: this.siteKeyValue,
      theme: this.themeValue,
      size: this.sizeValue,
      action: this.actionValue,
      language: this.languageValue,
      callback: () => {
        this.button.disabled = false;
        this.button.removeAttribute("title");
      },
    });
  }

  disconnect() {
    clearInterval(this.interval);

    // The widget lives inside Cloudflare's own iframe, which a removed element does
    // not take with it.
    if (this.widgetId !== undefined) {
      window.turnstile.remove(this.widgetId);
      this.widgetId = undefined;
    }
  }

  async loadScript() {
    return new Promise((resolve) => {
      if (document.querySelector("script[src*='turnstile/v0/api.js']") !== null) {
        resolve();
        return;
      }

      const script = document.createElement("script");
      script.src = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
      script.async = true;
      script.defer = true;
      script.onload = resolve;
      document.head.appendChild(script);
    });
  }

  async waitForTurnstile() {
    return new Promise((resolve) => {
      if (typeof window.turnstile !== "undefined") {
        resolve(true);
        return;
      }

      const startedAt = performance.now();

      this.interval = setInterval(() => {
        if (typeof window.turnstile !== "undefined") {
          clearInterval(this.interval);
          resolve(true);
          return;
        }

        if (performance.now() - startedAt > TURNSTILE_TIMEOUT_MS) {
          clearInterval(this.interval);
          resolve(false);
        }
      }, 100);
    });
  }

  get form() {
    return this.element.closest("form");
  }

  get button() {
    return this.form?.querySelector("input[type=submit], button[type=submit]") ?? null;
  }
}
