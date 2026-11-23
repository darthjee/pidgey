/**
 * Client for the Pidgey `/v1` API.
 *
 * This is a stub: it only stores its options. Validation and `send` come later.
 */
export class PidgeyClient {
  /**
   * Builds a PidgeyClient.
   * @param {object} [options] - Client options; a shallow copy is stored, frozen.
   */
  constructor(options = {}) {
    this.options = Object.freeze({ ...options });
  }
}
