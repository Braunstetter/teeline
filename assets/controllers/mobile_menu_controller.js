import {Controller} from "@hotwired/stimulus"
import {toggle} from "el-transition";
import {useClickOutside} from 'stimulus-use'

export default class extends Controller {
  static targets = [
    'menu',
    'button',
    'openIcon',
    'closedIcon',
  ]

  static classes = ['hidden']

  isLocked = false;
  isOpen = false;

  connect() {
    useClickOutside(this, {element: this.menuTarget})

    this.buttonTarget.setAttribute('aria-expanded', String(this.isOpen))
    this.bindEvents();
  }

  disconnect() {
    this.unbindEvents();
  }

  async clickOutside(event) {
    if (!this.isOpen) {
      return;
    }

    await this.toggle();
  }

  async toggle() {
    if (this.isLocked) {
      return;
    }

    this.isLocked = true;

    try {
      await toggle(this.menuTarget)
      this.isOpen = !this.isOpen
      this.buttonTarget.setAttribute('aria-expanded', String(this.isOpen))
      this.openIconTarget.classList.toggle(this.hiddenClass)
      this.closedIconTarget.classList.toggle(this.hiddenClass)
    } finally {
      this.isLocked = false
    }
  }

  async close() {
    if (!this.isOpen || this.isLocked) {
      return;
    }
    await this.toggle()
  }

  resetMenu() {
    // Force menu to closed state without animation for Turbo navigation
    this.menuTarget.classList.add(this.hiddenClass)
    this.openIconTarget.classList.add(this.hiddenClass)
    this.closedIconTarget.classList.remove(this.hiddenClass)
    this.buttonTarget.setAttribute('aria-expanded', 'false')
    this.isOpen = false
    this.isLocked = false
  }

  handleKeyDown = async (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    await this.clickOutside(event);
  }

  bindEvents() {
    // Button
    this._onButtonClick = (event) => { event.preventDefault(); this.toggle(); };
    this.buttonTarget.addEventListener('click', this._onButtonClick);

    // Turbo
    this._onTurboBeforeVisit = () => { if (this.isOpen) this.resetMenu(); };
    document.addEventListener('turbo:before-visit', this._onTurboBeforeVisit);
    this._onTurboVisit = () => { if (this.isOpen) this.resetMenu(); };
    document.addEventListener('turbo:visit', this._onTurboVisit);

    // Menü
    this._onMenuClick = (event) => {
      if (event.target.closest('a[href]') && this.isOpen) this.resetMenu();
    };

    this.menuTarget.addEventListener('click', this._onMenuClick);

    // Keydown
    window.addEventListener('keydown', this.handleKeyDown);
  }

  unbindEvents() {
    this.buttonTarget?.removeEventListener('click', this._onButtonClick);
    this.menuTarget?.removeEventListener('click', this._onMenuClick);
    document.removeEventListener('turbo:before-visit', this._onTurboBeforeVisit);
    document.removeEventListener('turbo:visit', this._onTurboVisit);
    window.removeEventListener('keydown', this.handleKeyDown);

    this._onButtonClick = null;
    this._onMenuClick = null;
    this._onTurboBeforeVisit = null;
    this._onTurboVisit = null;
  }
}
