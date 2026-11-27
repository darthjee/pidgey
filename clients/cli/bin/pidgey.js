#!/usr/bin/env node
import { run } from '../cli/run.js';

const controller = new AbortController();

process.exitCode = await run(process.argv.slice(2), {
  env: process.env,
  stdin: process.stdin,
  stdout: process.stdout,
  stderr: process.stderr,
  signal: controller.signal,
  platform: process.platform,
});
