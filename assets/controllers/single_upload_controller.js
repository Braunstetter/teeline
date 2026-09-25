import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ['container', 'field', 'addButton', 'trashButton']

  static classes = ['disabled']

  static values = {
    prototype: String,
  }


  clear(event) {
    this.removeItem(event);
    this.doAddItem();
  }

  removeItem(event) {
    event.preventDefault();
    this.fieldTarget.remove();
  }

  doAddItem() {
    const prototype = this.prototypeValue;
    const newField = prototype
      .replace(/__name___/g, '')
      .replace(/\[__name__]/g, '');


    this.containerTarget.insertAdjacentHTML('beforeend', newField)
  }

  onImageUploaded(event) {
    // Enable the trash button when an image is uploaded
    if (this.hasTrashButtonTarget) {
      this.trashButtonTarget.disabled = false;
      this.trashButtonTarget.removeAttribute('aria-disabled');
      this.trashButtonTarget.classList.remove(...this.disabledClasses);
    }
  }

}
