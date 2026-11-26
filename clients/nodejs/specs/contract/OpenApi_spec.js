import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';
import openapiTS, { COMMENT_HEADER, astToString } from 'openapi-typescript';
import { PidgeyClient } from '../../lib/PidgeyClient.js';
import { CONTRACT_FIXTURES } from '../support/Fixtures.js';
import { TestServer } from '../support/TestServer.js';

const DOCUMENT_URL = new URL('../../../../source/openapi/v1.json', import.meta.url);
const TYPES_URL = new URL('../../types/v1.ts', import.meta.url);
const DOCUMENT_ID = 'https://pidgey.test/openapi/v1.json';
const SEND_RESPONSES = (document) => document.paths['/v1/messages'].post.responses;

describe('The /v1 OpenAPI document', () => {
  const document = JSON.parse(readFileSync(DOCUMENT_URL, 'utf8'));
  // `strict: false` tolerates the OpenAPI keywords JSON Schema does not know, such as `example`.
  const ajv = new Ajv2020({ strict: false });
  addFormats(ajv);
  ajv.addSchema({ ...document, $id: DOCUMENT_ID });

  const validatorFor = (name) => ajv.getSchema(`${DOCUMENT_ID}#/components/schemas/${name}`);
  const resolve = (node) => (node.$ref ? resolve(pointer(node.$ref)) : node);
  const pointer = (ref) => ref.slice(2).split('/').reduce((node, key) => node[key], document);

  // The schema the document gives a status in `POST /v1/messages`. A status the document does
  // not list (`429`, for instance) is still an error envelope, so it falls back to `Error`.
  const schemaNameFor = (status) => {
    const response = SEND_RESPONSES(document)[String(status)];
    const schema = response && resolve(response).content['application/json'].schema;
    return schema ? schema.$ref.split('/').pop() : 'Error';
  };

  const expectValid = (validate, value) => {
    expect(validate(value)).withContext(JSON.stringify(validate.errors)).toBe(true);
  };

  describe('request bodies sent by the client', () => {
    let server;

    beforeEach(async () => {
      server = await TestServer.start({ json: { status: 'sent', smtp: { code: 250, message: 'OK' } } });
    });

    afterEach(() => server.stop());

    const send = (message, options) => new PidgeyClient({ token: 'pdg_secret_token', baseUrl: server.baseUrl })
      .send(message, options);

    [
      ['text only', { to: 'user@example.com', subject: 'Hello', text: 'Hi!' }],
      ['html only', { to: 'user@example.com', subject: 'Hello', html: '<p>Hi!</p>' }],
      ['text and html', { to: 'user@example.com', subject: 'Hello', text: 'Hi!', html: '<p>Hi!</p>' }],
    ].forEach(([name, message]) => {
      it(`matches SendMessageRequest with ${name}`, async () => {
        await send(message);

        expectValid(validatorFor('SendMessageRequest'), JSON.parse(server.requests[0].body));
      });
    });

    it('matches SendMessageRequest and the Idempotency-Key header with an idempotencyKey', async () => {
      const parameter = document.paths['/v1/messages'].post.parameters
        .find((candidate) => candidate.name === 'Idempotency-Key');
      const key = '0b6c1e0a-5d0e-4a5b-9d0e-3f0a2f8c1b7d';

      await send({ to: 'user@example.com', subject: 'Hello', text: 'Hi!' }, { idempotencyKey: key });

      expectValid(validatorFor('SendMessageRequest'), JSON.parse(server.requests[0].body));
      expectValid(ajv.compile(parameter.schema), server.requests[0].headers['idempotency-key']);
      expect(server.requests[0].headers['idempotency-key']).toBe(key);
    });

    it('sends a generated Idempotency-Key that matches the header schema', async () => {
      const parameter = document.paths['/v1/messages'].post.parameters
        .find((candidate) => candidate.name === 'Idempotency-Key');

      await send({ to: 'user@example.com', subject: 'Hello', text: 'Hi!' });

      expectValid(ajv.compile(parameter.schema), server.requests[0].headers['idempotency-key']);
    });
  });

  describe('response fixtures', () => {
    CONTRACT_FIXTURES.forEach(({ name, reply }) => {
      it(`${name} matches the schema of its status`, () => {
        expectValid(validatorFor(schemaNameFor(reply.status)), reply.json);
      });
    });

    it('rejects a body that does not match, so the check can fail', () => {
      expect(validatorFor('SendMessageResponse')({ status: 'sent' })).toBe(false);
    });
  });

  describe('types/v1.ts', () => {
    it('is the output of openapi-typescript for the document (run `yarn generate:types` if not)', async () => {
      // The same as the `openapi-typescript` CLI: its comment header, then the generated source.
      const generated = `${COMMENT_HEADER}${astToString(await openapiTS(pathToFileURL(DOCUMENT_URL.pathname), { silent: true }))}`;

      expect(generated)
        .withContext('types/v1.ts is out of date: run `yarn generate:types` and commit the result')
        .toBe(readFileSync(TYPES_URL, 'utf8'));
    });
  });
});
