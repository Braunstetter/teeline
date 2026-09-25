import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ['closeBtn']
  static values = {timeToDestroy: String}

  async connect() {
    await this.show()
  }

  async show() {
    this.element.classList.remove('hidden');
    await this.animationsComplete(this.element)

    if (!this.hasTimeToDestroyValue === true) {
      return;
    }

    setTimeout(() => {
      this.close();
    }, this.timeToDestroyValueOrDefault * 1000);
  }

  async close() {

    this.element.classList.add('animate-fade-out')

    await this.animationsComplete(this.element)
    this.element.classList.remove('animate-fade-out')
    this.element.classList.add('hidden', 'animate-slide-in')
  }

  async animationsComplete() {
    await Promise.allSettled(
      this.element.getAnimations().map(animation =>
        animation.finished))
  }

  get timeToDestroyValueOrDefault() {
    return this.hasTimeToDestroyValue ? this.timeToDestroyValue : 5;
  }

}
