import { Controller } from "@hotwired/stimulus";
import { useClickOutside, useTransition } from "stimulus-use";
import { computePosition, flip, offset } from "@floating-ui/dom";
import { toggle, leave } from "el-transition";

export default class extends Controller {
  static targets = ["opener", "content", "arrow"];
  #open = false;

  connect() {
    useClickOutside(this);
    useTransition(this, { element: this.contentTarget });

    if (!this.hasOpenerTarget) return;

    this.openerTarget.addEventListener("click", () => this.toggleDropdown());
    document.addEventListener("keydown", (e) => this.handleKeydown(e));
    document.addEventListener("modal:closed", () => this.closeDropdown());
  }

  async toggleDropdown() {
    !this.#open && await this.updatePosition();
    await toggle(this.contentTarget);
    this.toggleState();
    this.#open && this.focusFirstItem();
  }

  async closeDropdown() {
    if (!this.#open) return;
    await leave(this.contentTarget);
    this.toggleState();
    this.openerTarget.focus();
  }

  async clickOutside(event) {
    if (!this.#open || this.isSomeDialogOpen()) return;
    event.preventDefault();
    await this.closeDropdown();
  }

  handleKeydown(event) {
    if (event.key === "Escape" && this.#open) {
      event.preventDefault();
      this.closeDropdown();
      return;
    }

    if (!this.#open) return;

    const items = this.getFocusableItems();
    const currentIndex = items.indexOf(document.activeElement);

    switch (event.key) {
      case "ArrowDown":
        event.preventDefault();
        this.focusItem(items, currentIndex + 1);
        break;
      case "ArrowUp":
        event.preventDefault();
        this.focusItem(items, currentIndex - 1);
        break;
      case "Home":
        event.preventDefault();
        this.focusItem(items, 0);
        break;
      case "End":
        event.preventDefault();
        this.focusItem(items, items.length - 1);
        break;
      case "Tab":
        // Close dropdown on Tab to allow natural tab flow
        this.closeDropdown();
        break;
    }
  }

  getFocusableItems() {
    return [...this.contentTarget.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])')];
  }

  focusFirstItem() {
    this.getFocusableItems()[0]?.focus();
  }

  focusItem(items, index) {
    if (!items.length) return;
    items[((index % items.length) + items.length) % items.length].focus();
  }

  toggleState() {
    this.#open = !this.#open;
    this.updateTargetAttributes();
  }

  updateTargetAttributes() {
    this.openerTarget.setAttribute("aria-expanded", this.#open);
    this.hasArrowTarget && this.arrowTarget.classList.toggle("-rotate-180", this.#open);
  }

  async updatePosition() {
    // Make element measurable (remove display:none, hide visually)
    this.contentTarget.classList.remove('hidden');
    this.contentTarget.style.visibility = 'hidden';

    const { x, y } = await computePosition(this.openerTarget, this.contentTarget, {
      placement: "bottom-end",
      middleware: [offset(8), flip()],
    });

    Object.assign(this.contentTarget.style, { left: `${x}px`, top: `${y}px`, visibility: '' });

    // Restore hidden for transition
    this.contentTarget.classList.add('hidden');
  }

  isSomeDialogOpen() {
    return !!document.querySelector("[data-turbo-confirm-target='dialog']:not(.hidden)");
  }
}
