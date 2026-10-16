import { Controller } from '@hotwired/stimulus';

/*
 * THE WEEK WALK'S ONE HAND: the page is whole as links and a form, and this
 * controller only follows the controls to the address they already stand
 * for — the select and the checkbox submit the form, the range input writes
 * its value into the select first, so a slide lands on the same address a
 * click would. No state of its own, nothing drawn.
 */
export default class extends Controller {
    go() {
        this.element.requestSubmit();
    }

    slide(event) {
        const select = this.element.querySelector('select');
        if (select) {
            select.value = event.target.value;
        }
        this.element.requestSubmit();
    }
}
