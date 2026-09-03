// JSDOM (Vitest's test environment) does not implement the native <dialog>
// element's showModal()/close() methods - calling either throws
// "... is not a function". Every component built on Components/Modal.vue
// (DialogModal.vue and everything using it) calls these directly, so any
// test that mounts one needs this polyfill, not a per-file workaround.
if (typeof HTMLDialogElement !== 'undefined') {
    if (!HTMLDialogElement.prototype.showModal) {
        HTMLDialogElement.prototype.showModal = function () {
            this.open = true;
        };
    }

    if (!HTMLDialogElement.prototype.close) {
        HTMLDialogElement.prototype.close = function () {
            this.open = false;
        };
    }
}
