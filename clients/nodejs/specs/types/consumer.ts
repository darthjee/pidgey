// A strict TypeScript consumer of the installed `pidgey-client` package. It is compiled by the
// pack smoke test (never executed, never picked up by Jasmine) and has positive usage only:
// it fails to compile when the shipped declarations stop matching the public API.
import { PidgeyClient, PidgeyError, VERSION } from 'pidgey-client';

const version: string = VERSION;

const client: PidgeyClient = new PidgeyClient({
  token: 'pdg_example0123456789',
  baseUrl: 'https://pidgey.example.com',
  allowInsecureHttp: false,
  maxRetries: 2,
  retryDelayMs: 500,
  retryAmbiguous: false,
  timeoutMs: 30000,
});

async function main(): Promise<void> {
  try {
    const result = await client.send(
      { to: 'user@example.com', subject: 'Hello', text: 'Hi!' },
      { signal: AbortSignal.timeout(1000), idempotencyKey: 'order-1234' },
    );
    const status: 'sent' = result.status;
    const code: number = result.smtp.code;
    const message: string = result.smtp.message;
    const replayed: boolean = result.replayed;
    console.log(version, status, code, message, replayed);
  } catch (err) {
    if (err instanceof PidgeyError) {
      const errorCode: string | undefined = err.code;
      const possiblySent: boolean = err.possiblySent;
      const smtpCode: number | undefined = err.smtp?.code;
      console.log(errorCode, possiblySent, smtpCode);
    }
  }
}

void main();
