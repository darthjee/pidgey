#!/usr/bin/env node
import { run } from '../cli/run.js';

process.exitCode = run(process.argv.slice(2), process);
