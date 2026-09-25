import {Controller} from "@hotwired/stimulus"
import * as Turbo from "@hotwired/turbo"
import DOMPurify from 'dompurify';

export default class extends Controller {

  static targets = [
    "dialog",
    "title",
    "body",
    "confirmButton",
    "cancelButton",
    "closeButton",
  ]

  static values = {
    title: {
      type: String,
      default: "Are you sure?"
    },
    body: {
      type: String,
      default: "This action can't be undone"
    },
    confirmButtonText: {
      type: String,
      default: "Yes, I'm sure"
    },
    cancelButtonText: {
      type: String,
      default: "Cancel"
    }
  }

  connect() {
    this.setupDialog()
    document.addEventListener('keydown', this.closeOnEsc)
  }

  // Turbo keeps controllers alive across navigations, so a listener left on the
  // document would pile up one copy per visit.
  disconnect() {
    document.removeEventListener('keydown', this.closeOnEsc)
  }

  setupDialog() {
    Turbo.config.forms.confirm = (message, element, submitter) => {
      // Use submitter if it has confirm attributes, otherwise use element (the form)
      const source = (submitter?.dataset.confirmTitle) ? submitter : element;
      let {
        confirmTitle: titleText,
        confirmBody: bodyText,
        confirmBtnText,
        cancelBtnText
      } = source.dataset

      this.titleTarget.innerText = titleText || this.titleValue
      this.bodyTarget.innerHTML = this.sanitize(bodyText || this.bodyValue)
      this.confirmButtonTarget.innerText = confirmBtnText || this.confirmButtonTextValue
      this.cancelButtonTarget.innerText = cancelBtnText || this.cancelButtonTextValue

      this.showModal();

      return new Promise((resolve) => {
        this.dialogTarget.addEventListener("close", () => {
          this.closeModal();
          resolve(this.dialogTarget.returnValue === "confirm")
        }, {once: true})
      })
    }
  }

  closeModal() {
    this.dialogTarget.classList.add("hidden")
    this.dialogTarget.setAttribute('inert', '')
    document.dispatchEvent(new CustomEvent('modal:closed'))
  }

  showModal() {
    this.dialogTarget.showModal()
    this.dialogTarget.classList.remove("hidden")
    this.dialogTarget.removeAttribute('inert')
    this.setupLightDismiss()
  }

  resetModal() {
    this.dialogTarget.returnValue = null;
  }

  confirmAction(event) {
    event.preventDefault();

    let target = event.target;
    if (target.tagName !== 'A' && target.tagName !== 'BUTTON') {
      target = target.closest('a, button');
    }

    this.titleTarget.innerText = target.dataset.confirmTitle || this.titleValue;
    this.bodyTarget.innerHTML = this.sanitize(target.dataset.confirmBody || this.bodyValue);
    this.confirmButtonTarget.innerText = target.dataset.confirmBtnText || this.confirmButtonTextValue;
    this.cancelButtonTarget.innerText = target.dataset.cancelBtnText || this.cancelButtonTextValue;

    this.showModal();

    return new Promise((resolve) => {
      this.dialogTarget.addEventListener("close", () => {
        this.closeModal();

        if (this.dialogTarget.returnValue !== "confirm") {
          this.resetModal();
          return;
        }

        // For links, navigate to the href
        if (target.tagName === 'A' && target.href) {
          this.resetModal();
          Turbo.visit(target.href);
          resolve();
          return;
        }

        // For custom events
        if (event.params?.eventName) {
          const customEvent = new Event(event.params.eventName, {bubbles: true});
          event.target.dispatchEvent(customEvent);
        }

        this.resetModal();
        resolve();
      }, {once: true});
    });
  }

  sanitize(bodyText) {
    return DOMPurify.sanitize(bodyText, {
      ALLOWED_TAGS: ['strong', 'em', 'b', 'i'],
      ALLOWED_ATTR: [],
      FORBID_TAGS: ['script', 'style']
    });
  }

  setupLightDismiss() {
    this.dialogTarget.addEventListener(
      "click",
      event => this.dialogTarget === event.target && this.dialogTarget.close("dismiss"),
    )
  }

  closeOnEsc = (event) => {
    if (event.key !== 'Escape') {
      return;
    }

      this.dialogTarget.close("dismiss")
  }

}
