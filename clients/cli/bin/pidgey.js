#!/usr/bin/env node
import { run } from '../cli/run.js';

const controller = new AbortController();
// `once`: a second SIGINT gets Node's default behaviour and ends the process at once.
process.once('SIGINT', () => controller.abort());

// No process.exit(): the process ends by itself once stdout is flushed and the library has
// left no socket open.
process.exitCode = await run(process.argv.slice(2), {
  env: process.env,
  stdin: process.stdin,
  stdout: process.stdout,
  stderr: process.stderr,
  signal: controller.signal,
});
