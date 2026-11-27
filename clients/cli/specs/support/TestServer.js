import http from 'node:http';

/**
 * A scripted in-process HTTP server on `127.0.0.1`, port `0`, for the subprocess specs.
 *
 * It records every request (`method`, `url`, `headers`, `body`) and answers from a reply
 * object `{ status, json, hold }`:
 * - `status` (default `200`) and `json`: the status and the JSON body of the answer;
 * - `hold`: answer nothing until `release()` is called, or until the client closes the
 *   connection (which is what a SIGINT in flight does).
 *
 * Answers are HTTP/1.1 keep-alive and the idle connection is left open (for a minute), so a
 * client that waits on an idle socket would hang. `stop()` destroys every open socket.
 */
export class TestServer {
  /** The recorded requests. */
  requests = [];

  /** Resolves with the first request, as soon as it has been received completely. */
  firstRequest;

  /** Resolves when a request's connection is closed by the client before the answer. */
  clientClosed;

  #server;
  #reply;
  #held = [];
  #sockets = new Set();
  #resolveFirst;
  #resolveClosed;

  /**
   * Starts a server.
   * @param {{ status?: number, json?: object, hold?: boolean }} [reply] - The scripted answer.
   * @returns {Promise<TestServer>} The listening server.
   */
  static async start(reply = { json: { status: 'sent', smtp: { code: 250, message: 'OK' } } }) {
    const server = new TestServer(reply);
    await new Promise((resolve) => server.#server.listen(0, '127.0.0.1', resolve));
    return server;
  }

  constructor(reply) {
    this.#reply = reply;
    this.firstRequest = new Promise((resolve) => { this.#resolveFirst = resolve; });
    this.clientClosed = new Promise((resolve) => { this.#resolveClosed = resolve; });
    this.#server = http.createServer((req, res) => this.#handle(req, res));
    this.#server.keepAliveTimeout = 60000;
    this.#server.on('connection', (socket) => {
      this.#sockets.add(socket);
      socket.on('close', () => this.#sockets.delete(socket));
    });
  }

  /** The `http://127.0.0.1:<port>` URL of the server. */
  get baseUrl() {
    return `http://127.0.0.1:${this.#server.address().port}`;
  }

  /** Answers the requests that are being held. */
  release() {
    for (const send of this.#held.splice(0)) {
      send();
    }
  }

  /** Stops the server, destroying every open socket first, idle keep-alive ones included. */
  async stop() {
    for (const socket of this.#sockets) {
      socket.destroy();
    }
    await new Promise((resolve) => this.#server.close(resolve));
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
      this.#resolveFirst(request);

      const send = () => {
        const payload = JSON.stringify(this.#reply.json ?? {});
        res.writeHead(this.#reply.status ?? 200, {
          'content-type': 'application/json',
          'content-length': Buffer.byteLength(payload),
        });
        res.end(payload);
      };
      if (this.#reply.hold) {
        this.#held.push(send);
        res.on('close', () => {
          if (!res.writableEnded) {
            this.#resolveClosed();
          }
        });
      } else {
        send();
      }
    });
  }
}
