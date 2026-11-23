import http from 'node:http';
import { gzipSync } from 'node:zlib';

/**
 * A scripted in-process HTTP server on `127.0.0.1`, port `0`, for the specs.
 *
 * It records every request and answers from a script: one reply object, an array of replies
 * (consumed in order, the last one repeating) or a function `(request) => reply`.
 *
 * A reply is `{ status, headers, json, body, gzip, delay, destroy, stall }`:
 * - `json`: an object sent as the JSON body; `body`: a string or Buffer sent as is;
 * - `gzip`: compress the body with `Content-Encoding: gzip`;
 * - `delay`: milliseconds to wait before answering;
 * - `destroy`: destroy the socket on receiving the request, answering nothing;
 * - `stall`: send the status and the first bytes of the body, then never finish.
 */
export class TestServer {
  /** The recorded requests: `{ method, url, headers, body }`. */
  requests = [];

  #server;
  #script;
  #timers = new Set();

  /**
   * Starts a server.
   * @param {object | object[] | ((request: object) => object)} [script] - How to answer.
   * @returns {Promise<TestServer>} The listening server.
   */
  static async start(script = { json: { status: 'sent' } }) {
    const server = new TestServer(script);
    await new Promise((resolve) => server.#server.listen(0, '127.0.0.1', resolve));
    return server;
  }

  constructor(script) {
    this.#script = script;
    this.#server = http.createServer((req, res) => this.#handle(req, res));
  }

  /** The `http://127.0.0.1:<port>` URL of the server. */
  get baseUrl() {
    return `http://127.0.0.1:${this.#server.address().port}`;
  }

  /** Stops the server, closing every connection first, idle keep-alive ones included. */
  async stop() {
    this.#timers.forEach(clearTimeout);
    this.#server.closeAllConnections();
    await new Promise((resolve) => this.#server.close(resolve));
  }

  #pick(request) {
    if (typeof this.#script === 'function') {
      return this.#script(request);
    }
    if (Array.isArray(this.#script)) {
      return this.#script[Math.min(this.requests.length - 1, this.#script.length - 1)];
    }
    return this.#script;
  }

  #handle(req, res) {
    const chunks = [];
    req.on('data', (chunk) => chunks.push(chunk));
    req.on('end', () => {
      const request = {
        method: req.method,
        url: req.url,
        headers: req.headers,
        body: Buffer.concat(chunks).toString('utf8'),
      };
      this.requests.push(request);
      const reply = this.#pick(request);
      if (reply.destroy) {
        req.socket.destroy();
        return;
      }
      const timer = setTimeout(() => this.#send(res, reply), reply.delay ?? 0);
      this.#timers.add(timer);
    });
  }

  #send(res, reply) {
    let payload = reply.body ?? (reply.json === undefined ? '' : JSON.stringify(reply.json));
    const headers = { ...reply.headers };
    if (reply.json !== undefined) {
      headers['content-type'] = 'application/json';
    }
    if (reply.gzip) {
      payload = gzipSync(payload);
      headers['content-encoding'] = 'gzip';
    }
    if (reply.stall) {
      res.writeHead(reply.status ?? 200, headers);
      res.write(payload.slice(0, 4));
      return;
    }
    res.writeHead(reply.status ?? 200, { ...headers, 'content-length': Buffer.byteLength(payload) });
    res.end(payload);
  }
}
