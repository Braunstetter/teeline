import {Controller} from "@hotwired/stimulus"
import party from "party-js"

export default class extends Controller {
  connect() {
    party.settings.gravity = 150
    party.confetti(document.body, {count: 200, speed: 300})
  }
}
